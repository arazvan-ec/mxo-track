<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Domain\Event\RouteReoptimized;
use App\Domain\Event\StopDelivered;
use App\Domain\Route\Model\Route;
use App\Domain\Route\Repository\RouteEventRepositoryInterface;
use App\Entity\VehicleLastPosition;
use App\Enum\RouteEventType;
use App\Enum\RouteStatus;
use App\Repository\RouteRepository;
use App\Service\RouteOptimizationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Automatically re-optimizes pending stops when accumulated delay
 * exceeds a threshold. Includes cooldown to prevent rapid-fire re-opts.
 */
final readonly class DelayReoptimizationSubscriber
{
    public function __construct(
        private RouteRepository $routeRepo,
        private RouteOptimizationService $optimizer,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private EventDispatcherInterface $eventDispatcher,
        private RouteEventRepositoryInterface $eventRepo,
        private int $delayThresholdMinutes = 30,
        private int $cooldownMinutes = 10,
    ) {}

    #[AsEventListener]
    public function onStopDelivered(StopDelivered $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);
        if (!$route instanceof Route) {
            return;
        }

        if (!$route->isAutoReoptimize()) {
            return;
        }

        if ($route->getStatus() !== RouteStatus::ACTIVE) {
            return;
        }

        if (!$this->isDelayExceeded($route)) {
            return;
        }

        if ($this->isInCooldown($route)) {
            return;
        }

        $currentLat = null;
        $currentLng = null;
        $vehicle = $route->getVehicle();

        if ($vehicle !== null) {
            $lastPosition = $this->em->getRepository(VehicleLastPosition::class)
                ->findOneBy(['vehicle' => $vehicle]);

            if ($lastPosition instanceof VehicleLastPosition) {
                $currentLat = $lastPosition->getLat();
                $currentLng = $lastPosition->getLng();
            }
        }

        try {
            $result = $this->optimizer->reoptimizePendingStops($route, $currentLat, $currentLng);
            $this->optimizer->applyOptimizedOrder($result['optimized']);

            $distanceBefore = $result['distanceBefore'];
            $distanceAfter = $result['distanceAfter'];
            $improvement = $distanceBefore > 0 ? (1 - $distanceAfter / $distanceBefore) * 100 : 0;

            $this->eventDispatcher->dispatch(new RouteReoptimized(
                routePublicId: $event->routePublicId,
                improvementPercent: $improvement,
                distanceKm: $distanceAfter,
                durationMinutes: $result['durationMinutes'],
                pendingStopsCount: \count($result['optimized']),
                trigger: 'delay',
            ));

            $this->logger->info('Auto-reoptimized route due to delay.', [
                'route_public_id' => $event->routePublicId,
                'stops_reordered' => \count($result['optimized']),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Auto-reoptimization failed due to delay.', [
                'route_public_id' => $event->routePublicId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function isDelayExceeded(Route $route): bool
    {
        $estimatedMinutes = $route->getEstimatedDurationMinutes();
        if ($estimatedMinutes === null || $estimatedMinutes <= 0) {
            return false;
        }

        $startEvent = $this->eventRepo->findLastByTypeForRoute($route, RouteEventType::STARTED);
        if ($startEvent === null) {
            return false;
        }

        $startedAt = $startEvent->getCreatedAt();
        $now = new \DateTimeImmutable();
        $elapsedMinutes = ($now->getTimestamp() - $startedAt->getTimestamp()) / 60;

        $delayMinutes = $elapsedMinutes - $estimatedMinutes;

        return $delayMinutes >= $this->delayThresholdMinutes;
    }

    private function isInCooldown(Route $route): bool
    {
        $lastReopt = $this->eventRepo->findLastByTypeForRoute($route, RouteEventType::REOPTIMIZED);
        if ($lastReopt === null) {
            return false;
        }

        $now = new \DateTimeImmutable();
        $minutesSinceLastReopt = ($now->getTimestamp() - $lastReopt->getCreatedAt()->getTimestamp()) / 60;

        return $minutesSinceLastReopt < $this->cooldownMinutes;
    }
}
