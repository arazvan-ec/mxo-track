<?php

declare(strict_types=1);

namespace App\EventListener\Domain;

use App\Domain\Route\Event\DeviationDetected;
use App\Domain\Route\Event\DeviationEnded;
use App\Domain\Route\Event\EtaChanged;
use App\Domain\Route\Event\RouteAssigned;
use App\Domain\Route\Event\RouteCancelled;
use App\Domain\Route\Event\RouteCompleted;
use App\Domain\Route\Event\RouteOptimized;
use App\Domain\Route\Event\RouteStarted;
use App\Domain\Route\Event\RoutesBuilt;
use App\Domain\Event\StopDelivered;
use App\Domain\Event\StopExceptionReported;
use App\Entity\RouteEvent;
use App\Enum\RouteEventType;
use App\Enum\RouteStopStatus;
use App\Repository\RouteRepository;
use App\Repository\RouteStopRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final readonly class RouteEventLogListener
{
    public function __construct(
        private EntityManagerInterface $em,
        private RouteRepository $routeRepo,
        private UserRepository $userRepo,
        private RouteStopRepository $stopRepo,
        private HubInterface $hub,
    ) {}

    #[AsEventListener]
    public function onRoutesBuilt(RoutesBuilt $event): void
    {
        foreach ($event->routePublicIds as $routePublicId) {
            $route = $this->routeRepo->findOneByPublicId($routePublicId);
            if (!$route) {
                continue;
            }

            $this->persistAndPublish(new RouteEvent(
                route: $route,
                eventType: RouteEventType::CREATED,
                actorType: 'system',
                payload: [
                    'shipment_count' => $event->shipmentCount,
                    'vehicle_count' => $event->vehicleCount,
                ],
                snapshotMetrics: $this->buildSnapshotMetrics($route),
                occurredAt: $event->occurredAt,
            ), $routePublicId);
        }
    }

    #[AsEventListener]
    public function onRouteOptimized(RouteOptimized $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);
        if (!$route) {
            return;
        }

        $this->persistAndPublish(new RouteEvent(
            route: $route,
            eventType: RouteEventType::OPTIMIZED,
            actorType: 'system',
            payload: [
                'improvement_percent' => $event->improvementPercent,
                'distance_km' => $event->distanceKm,
                'duration_minutes' => $event->durationMinutes,
            ],
            snapshotMetrics: $this->buildSnapshotMetrics($route),
            occurredAt: $event->occurredAt,
        ), $event->routePublicId);
    }

    #[AsEventListener]
    public function onRouteStarted(RouteStarted $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);
        if (!$route) {
            return;
        }

        $actor = $this->userRepo->find($event->driverUserId);

        $this->persistAndPublish(new RouteEvent(
            route: $route,
            eventType: RouteEventType::STARTED,
            actorType: 'driver',
            actorUser: $actor,
            payload: ['driver_user_id' => $event->driverUserId],
            snapshotMetrics: $this->buildSnapshotMetrics($route),
            occurredAt: $event->occurredAt,
        ), $event->routePublicId);
    }

    #[AsEventListener]
    public function onRouteCompleted(RouteCompleted $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);
        if (!$route) {
            return;
        }

        $actor = $this->userRepo->find($event->driverUserId);

        $this->persistAndPublish(new RouteEvent(
            route: $route,
            eventType: RouteEventType::COMPLETED,
            actorType: 'driver',
            actorUser: $actor,
            payload: ['driver_user_id' => $event->driverUserId],
            snapshotMetrics: $this->buildSnapshotMetrics($route),
            occurredAt: $event->occurredAt,
        ), $event->routePublicId);
    }

    #[AsEventListener]
    public function onStopDelivered(StopDelivered $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);
        if (!$route) {
            return;
        }

        $actor = $this->userRepo->find($event->driverUserId);

        $this->persistAndPublish(new RouteEvent(
            route: $route,
            eventType: RouteEventType::STOP_DELIVERED,
            actorType: 'driver',
            actorUser: $actor,
            payload: [
                'stop_public_id' => $event->stopPublicId,
                'shipment_public_id' => $event->shipmentPublicId,
                'pod_public_id' => $event->podPublicId,
            ],
            snapshotMetrics: $this->buildSnapshotMetrics($route),
            occurredAt: $event->occurredAt,
        ), $event->routePublicId);
    }

    #[AsEventListener]
    public function onStopExceptionReported(StopExceptionReported $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);
        if (!$route) {
            return;
        }

        $actor = $this->userRepo->find($event->driverUserId);

        $this->persistAndPublish(new RouteEvent(
            route: $route,
            eventType: RouteEventType::STOP_EXCEPTION,
            actorType: 'driver',
            actorUser: $actor,
            payload: [
                'stop_public_id' => $event->stopPublicId,
                'exception_code' => $event->reason->value,
                'notes' => $event->notes,
            ],
            snapshotMetrics: $this->buildSnapshotMetrics($route),
            occurredAt: $event->occurredAt,
        ), $event->routePublicId);
    }

    #[AsEventListener]
    public function onRouteCancelled(RouteCancelled $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);
        if (!$route) {
            return;
        }

        $actor = $this->userRepo->find($event->cancelledByUserId);

        $this->persistAndPublish(new RouteEvent(
            route: $route,
            eventType: RouteEventType::CANCELLED,
            actorType: 'admin',
            actorUser: $actor,
            payload: ['reason' => $event->reason],
            snapshotMetrics: $this->buildSnapshotMetrics($route),
            occurredAt: $event->occurredAt,
        ), $event->routePublicId);
    }

    #[AsEventListener]
    public function onRouteAssigned(RouteAssigned $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);
        if (!$route) {
            return;
        }

        $actor = $this->userRepo->find($event->assignedByUserId);

        $this->persistAndPublish(new RouteEvent(
            route: $route,
            eventType: RouteEventType::ASSIGNED,
            actorType: 'admin',
            actorUser: $actor,
            payload: [
                'vehicle_public_id' => $event->vehiclePublicId,
                'driver_user_id' => $event->driverUserId,
            ],
            snapshotMetrics: $this->buildSnapshotMetrics($route),
            occurredAt: $event->occurredAt,
        ), $event->routePublicId);
    }

    #[AsEventListener]
    public function onEtaChanged(EtaChanged $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);
        if (!$route) {
            return;
        }

        $this->persistAndPublish(new RouteEvent(
            route: $route,
            eventType: RouteEventType::ETA_CHANGED,
            actorType: 'system',
            payload: [
                'max_delta_minutes' => $event->maxDeltaMinutes,
                'previous_etas' => $event->previousEtas,
                'current_etas' => $event->currentEtas,
            ],
            snapshotMetrics: $this->buildSnapshotMetrics($route),
            occurredAt: $event->occurredAt,
        ), $event->routePublicId);
    }

    #[AsEventListener]
    public function onDeviationDetected(DeviationDetected $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);
        if (!$route) {
            return;
        }

        $this->persistAndPublish(new RouteEvent(
            route: $route,
            eventType: RouteEventType::DEVIATION_DETECTED,
            actorType: 'system',
            payload: [
                'vehicle_public_id' => $event->vehiclePublicId,
                'latitude' => $event->latitude,
                'longitude' => $event->longitude,
                'distance_meters' => round($event->distanceMeters, 1),
                'threshold_meters' => $event->thresholdMeters,
            ],
            snapshotMetrics: $this->buildSnapshotMetrics($route),
            occurredAt: $event->occurredAt,
        ), $event->routePublicId);
    }

    #[AsEventListener]
    public function onDeviationEnded(DeviationEnded $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);
        if (!$route) {
            return;
        }

        $this->persistAndPublish(new RouteEvent(
            route: $route,
            eventType: RouteEventType::DEVIATION_ENDED,
            actorType: 'system',
            payload: [
                'vehicle_public_id' => $event->vehiclePublicId,
            ],
            snapshotMetrics: $this->buildSnapshotMetrics($route),
            occurredAt: $event->occurredAt,
        ), $event->routePublicId);
    }

    private function persistAndPublish(RouteEvent $routeEvent, string $routePublicId): void
    {
        $this->em->persist($routeEvent);
        $this->em->flush();

        try {
            $this->hub->publish(new Update(
                sprintf('/routes/%s/events', $routePublicId),
                json_encode([
                    'type' => $routeEvent->getEventType()->value,
                    'actor_type' => $routeEvent->getActorType(),
                    'actor_email' => $routeEvent->getActorUser()?->getEmail(),
                    'payload' => $routeEvent->getPayload(),
                    'snapshot_metrics' => $routeEvent->getSnapshotMetrics(),
                    'occurred_at' => $routeEvent->getOccurredAt()->format('c'),
                ], JSON_THROW_ON_ERROR),
            ));
        } catch (\Throwable) {
            // Mercure failure must not break event logging
        }
    }

    private function buildSnapshotMetrics(\App\Entity\Route $route): array
    {
        $stops = $this->stopRepo->findBy(['route' => $route]);

        $total = 0;
        $delivered = 0;
        $exceptions = 0;
        $pending = 0;

        foreach ($stops as $stop) {
            if ($stop->isOrigin()) {
                continue;
            }
            $total++;
            match ($stop->getStatus()) {
                RouteStopStatus::DELIVERED => $delivered++,
                RouteStopStatus::EXCEPTION => $exceptions++,
                default => $pending++,
            };
        }

        return [
            'total_stops' => $total,
            'delivered' => $delivered,
            'exceptions' => $exceptions,
            'pending' => $pending,
        ];
    }
}
