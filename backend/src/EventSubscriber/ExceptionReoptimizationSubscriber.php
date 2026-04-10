<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Domain\Event\RouteReoptimized;
use App\Domain\Event\StopExceptionReported;
use App\Domain\Route\Model\Route;
use App\Entity\VehicleLastPosition;
use App\Enum\RouteStatus;
use App\Repository\ReoptimizationPolicyRepository;
use App\Repository\RouteRepository;
use App\Service\RouteOptimizationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Automatically re-optimizes pending stops when a shipment exception is reported
 * on a route that has auto-reoptimization enabled.
 */
final readonly class ExceptionReoptimizationSubscriber
{
    public function __construct(
        private RouteRepository $routeRepo,
        private RouteOptimizationService $optimizer,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private EventDispatcherInterface $eventDispatcher,
        private ReoptimizationPolicyRepository $policyRepo,
    ) {}

    #[AsEventListener]
    public function onStopExceptionReported(StopExceptionReported $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);
        if (!$route instanceof Route) {
            return;
        }

        $policy = $this->policyRepo->findOneBy(['customer' => $route->getCustomer()]);
        if ($policy !== null) {
            if (!$policy->isEnabled() || !$policy->allowsTrigger('on_exception')) {
                return;
            }
        } elseif (!$route->isAutoReoptimize()) {
            return;
        }

        if ($route->getStatus() !== RouteStatus::ACTIVE) {
            return;
        }

        // Determine current driver position from vehicle's last known position
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
                trigger: 'exception',
            ));

            $this->logger->info('Auto-reoptimized route after exception.', [
                'route_public_id' => $event->routePublicId,
                'stop_public_id' => $event->stopPublicId,
                'reason' => $event->reason?->value ?? $event->exceptionCode,
                'stops_reordered' => \count($result['optimized']),
                'distance_before' => $distanceBefore,
                'distance_after' => $distanceAfter,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Auto-reoptimization failed after exception.', [
                'route_public_id' => $event->routePublicId,
                'stop_public_id' => $event->stopPublicId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
