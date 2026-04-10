<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Domain\Event\RouteCompleted;
use App\Domain\Route\Model\Route;
use App\Domain\Route\Repository\RouteRepositoryInterface;
use App\Domain\Route\Repository\RouteStopRepositoryInterface;
use App\Enum\RouteStopStatus;
use App\Repository\OptimizationStrategyComparisonRepository;
use App\Service\AddressRiskService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Reacts to RouteCompleted to:
 *  1. Update AddressRisk entries from the route's stops (learning delivery outcomes).
 *  2. Record actual outcome on any linked OptimizationStrategyComparison.
 */
final readonly class PostRouteUpdateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RouteRepositoryInterface $routeRepo,
        private RouteStopRepositoryInterface $stopRepo,
        private AddressRiskService $addressRiskService,
        private EntityManagerInterface $em,
        private OptimizationStrategyComparisonRepository $comparisonRepo,
        private LoggerInterface $logger,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            RouteCompleted::class => 'onRouteCompleted',
        ];
    }

    public function onRouteCompleted(RouteCompleted $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);

        if (!$route instanceof Route) {
            $this->logger->warning('PostRouteUpdateSubscriber: route not found.', [
                'route_public_id' => $event->routePublicId,
            ]);

            return;
        }

        $stops = $this->stopRepo->findByRoute($route);

        // Tarea 3a: update address risk from completed stops
        $this->addressRiskService->updateFromRouteStops($stops);

        // Tarea 3b: record outcome on linked OptimizationStrategyComparison
        $this->recordStrategyOutcome($route, $stops);
    }

    /**
     * @param list<\App\Domain\Route\Model\RouteStop> $stops
     */
    private function recordStrategyOutcome(Route $route, array $stops): void
    {
        $comparison = $this->comparisonRepo->findOneBy(['resultRoute' => $route]);

        if ($comparison === null) {
            return;
        }

        $deliveryCount = 0;
        $exceptionCount = 0;
        $skippedCount = 0;

        foreach ($stops as $stop) {
            match ($stop->getStatus()) {
                RouteStopStatus::DELIVERED => $deliveryCount++,
                RouteStopStatus::EXCEPTION => $exceptionCount++,
                RouteStopStatus::SKIPPED => $skippedCount++,
                default => null,
            };
        }

        $comparison->recordOutcome([
            'delivery_count' => $deliveryCount,
            'exception_count' => $exceptionCount,
            'skipped_count' => $skippedCount,
            'total_stops' => \count($stops),
        ]);

        $this->em->flush();
    }
}
