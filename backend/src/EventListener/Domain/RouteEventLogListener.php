<?php

declare(strict_types=1);

namespace App\EventListener\Domain;

use App\Domain\Event\RouteAssigned;
use App\Domain\Event\RouteCancelled;
use App\Domain\Event\RouteCompleted;
use App\Domain\Event\RouteOptimized;
use App\Domain\Event\RouteStarted;
use App\Domain\Event\RoutesBuilt;
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

final readonly class RouteEventLogListener
{
    public function __construct(
        private EntityManagerInterface $em,
        private RouteRepository $routeRepo,
        private UserRepository $userRepo,
        private RouteStopRepository $stopRepo,
    ) {}

    #[AsEventListener]
    public function onRoutesBuilt(RoutesBuilt $event): void
    {
        foreach ($event->routePublicIds as $routePublicId) {
            $route = $this->routeRepo->findOneByPublicId($routePublicId);
            if (!$route) {
                continue;
            }

            $this->em->persist(new RouteEvent(
                route: $route,
                eventType: RouteEventType::CREATED,
                actorType: 'system',
                payload: [
                    'shipment_count' => $event->shipmentCount,
                    'vehicle_count' => $event->vehicleCount,
                ],
                snapshotMetrics: $this->buildSnapshotMetrics($route),
                occurredAt: $event->occurredAt,
            ));
        }
        $this->em->flush();
    }

    #[AsEventListener]
    public function onRouteOptimized(RouteOptimized $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);
        if (!$route) {
            return;
        }

        $this->em->persist(new RouteEvent(
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
        ));
        $this->em->flush();
    }

    #[AsEventListener]
    public function onRouteStarted(RouteStarted $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);
        if (!$route) {
            return;
        }

        $actor = $this->userRepo->find($event->driverUserId);

        $this->em->persist(new RouteEvent(
            route: $route,
            eventType: RouteEventType::STARTED,
            actorType: 'driver',
            actorUser: $actor,
            payload: ['driver_user_id' => $event->driverUserId],
            snapshotMetrics: $this->buildSnapshotMetrics($route),
            occurredAt: $event->occurredAt,
        ));
        $this->em->flush();
    }

    #[AsEventListener]
    public function onRouteCompleted(RouteCompleted $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);
        if (!$route) {
            return;
        }

        $actor = $this->userRepo->find($event->driverUserId);

        $this->em->persist(new RouteEvent(
            route: $route,
            eventType: RouteEventType::COMPLETED,
            actorType: 'driver',
            actorUser: $actor,
            payload: ['driver_user_id' => $event->driverUserId],
            snapshotMetrics: $this->buildSnapshotMetrics($route),
            occurredAt: $event->occurredAt,
        ));
        $this->em->flush();
    }

    #[AsEventListener]
    public function onStopDelivered(StopDelivered $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);
        if (!$route) {
            return;
        }

        $actor = $this->userRepo->find($event->driverUserId);

        $this->em->persist(new RouteEvent(
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
        ));
        $this->em->flush();
    }

    #[AsEventListener]
    public function onStopExceptionReported(StopExceptionReported $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);
        if (!$route) {
            return;
        }

        $actor = $this->userRepo->find($event->driverUserId);

        $this->em->persist(new RouteEvent(
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
        ));
        $this->em->flush();
    }

    #[AsEventListener]
    public function onRouteCancelled(RouteCancelled $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);
        if (!$route) {
            return;
        }

        $actor = $this->userRepo->find($event->cancelledByUserId);

        $this->em->persist(new RouteEvent(
            route: $route,
            eventType: RouteEventType::CANCELLED,
            actorType: 'admin',
            actorUser: $actor,
            payload: ['reason' => $event->reason],
            snapshotMetrics: $this->buildSnapshotMetrics($route),
            occurredAt: $event->occurredAt,
        ));
        $this->em->flush();
    }

    #[AsEventListener]
    public function onRouteAssigned(RouteAssigned $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);
        if (!$route) {
            return;
        }

        $actor = $this->userRepo->find($event->assignedByUserId);

        $this->em->persist(new RouteEvent(
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
        ));
        $this->em->flush();
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
