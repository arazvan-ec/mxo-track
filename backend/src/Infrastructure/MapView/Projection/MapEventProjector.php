<?php

declare(strict_types=1);

namespace App\Infrastructure\MapView\Projection;

use App\Domain\Event\DeviationDetected;
use App\Domain\Event\DeviationEnded;
use App\Domain\Event\EtaChanged;
use App\Domain\Event\RouteAssigned;
use App\Domain\Event\RouteCancelled;
use App\Domain\Event\RouteCompleted;
use App\Domain\Event\RouteOptimized;
use App\Domain\Event\RoutesBuilt;
use App\Domain\Event\RouteStarted;
use App\Domain\Event\StopDelivered;
use App\Domain\Event\StopExceptionReported;
use App\Domain\Event\VehiclePositionReceived;
use App\Domain\MapView\Model\MapUpdate;
use App\Domain\MapView\Model\MapUpdateType;
use App\Domain\MapView\Model\VehiclePosition;
use App\Domain\MapView\Projection\MapProjectableEventInterface;
use App\Domain\MapView\Projection\MapProjectorInterface;
use App\Domain\MapView\Publisher\MapPublisherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Consolidates Mercure publishing for all map-relevant domain events.
 *
 * Replaces the publishing responsibility of:
 * - MercurePositionListener (vehicle positions)
 * - MercureRouteProgressListener (customer route progress)
 * - RouteSnapshotListener (route view updates)
 * - EtaRecalculationListener (ETA/deviation Mercure publishing)
 * - RouteEventLogListener (event log Mercure publishing)
 */
final class MapEventProjector implements MapProjectorInterface
{
    public function __construct(
        private readonly MapPublisherInterface $publisher,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    #[AsEventListener]
    public function onVehiclePositionReceived(VehiclePositionReceived $event): void
    {
        try {
            $position = $this->projectVehiclePosition($event);
            $this->publisher->publishVehiclePosition($position);
        } catch (\Throwable $e) {
            $this->logger->error('MapEventProjector: failed to publish vehicle position', [
                'vehiclePublicId' => $event->vehiclePublicId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    #[AsEventListener]
    public function onStopDelivered(StopDelivered $event): void
    {
        $this->publishFromEvent($event, MapUpdateType::StopDelivered, [
            'stopPublicId' => $event->stopPublicId,
            'shipmentPublicId' => $event->shipmentPublicId,
            'podPublicId' => $event->podPublicId,
        ]);
    }

    #[AsEventListener]
    public function onStopExceptionReported(StopExceptionReported $event): void
    {
        $this->publishFromEvent($event, MapUpdateType::StopException, [
            'stopPublicId' => $event->stopPublicId,
            'reason' => $event->reason->value,
        ]);
    }

    #[AsEventListener]
    public function onRouteStarted(RouteStarted $event): void
    {
        $this->publishFromEvent($event, MapUpdateType::RouteStarted);
    }

    #[AsEventListener]
    public function onRouteCompleted(RouteCompleted $event): void
    {
        $this->publishFromEvent($event, MapUpdateType::RouteCompleted);
    }

    #[AsEventListener]
    public function onRouteCancelled(RouteCancelled $event): void
    {
        $this->publishFromEvent($event, MapUpdateType::RouteCancelled, [
            'reason' => $event->reason,
        ]);
    }

    #[AsEventListener]
    public function onRouteOptimized(RouteOptimized $event): void
    {
        $this->publishFromEvent($event, MapUpdateType::RouteOptimized, [
            'improvementPercent' => $event->improvementPercent,
            'distanceKm' => $event->distanceKm,
            'durationMinutes' => $event->durationMinutes,
        ]);
    }

    #[AsEventListener]
    public function onRouteAssigned(RouteAssigned $event): void
    {
        $this->publishFromEvent($event, MapUpdateType::RouteAssigned, [
            'vehiclePublicId' => $event->vehiclePublicId,
        ]);
    }

    #[AsEventListener]
    public function onRoutesBuilt(RoutesBuilt $event): void
    {
        foreach ($event->routePublicIds as $routePublicId) {
            $update = new MapUpdate(
                type: MapUpdateType::RoutesBuilt,
                routePublicId: $routePublicId,
                data: [
                    'routeCount' => \count($event->routePublicIds),
                    'shipmentCount' => $event->shipmentCount,
                    'vehicleCount' => $event->vehicleCount,
                ],
                occurredAt: $event->occurredAt,
            );

            try {
                $this->publisher->publishRouteUpdate($update);
            } catch (\Throwable $e) {
                $this->logger->error('MapEventProjector: failed to publish routes_built', [
                    'routePublicId' => $routePublicId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    #[AsEventListener]
    public function onEtaChanged(EtaChanged $event): void
    {
        $stops = [];
        foreach ($event->currentEtas as $stopPublicId => $minutes) {
            $stops[] = [
                'stopPublicId' => $stopPublicId,
                'etaMinutes' => $minutes,
                'previousEtaMinutes' => $event->previousEtas[$stopPublicId] ?? null,
            ];
        }

        $this->publishFromEvent($event, MapUpdateType::EtaChanged, [
            'stops' => $stops,
            'maxDeltaMinutes' => $event->maxDeltaMinutes,
        ]);
    }

    #[AsEventListener]
    public function onDeviationDetected(DeviationDetected $event): void
    {
        $this->publishFromEvent($event, MapUpdateType::DeviationDetected, [
            'vehiclePublicId' => $event->vehiclePublicId,
            'lat' => $event->latitude,
            'lng' => $event->longitude,
            'distanceMeters' => $event->distanceMeters,
            'thresholdMeters' => $event->thresholdMeters,
        ]);
    }

    #[AsEventListener]
    public function onDeviationEnded(DeviationEnded $event): void
    {
        $this->publishFromEvent($event, MapUpdateType::DeviationEnded, [
            'vehiclePublicId' => $event->vehiclePublicId,
        ]);
    }

    public function projectRouteEvent(MapProjectableEventInterface $event): array
    {
        $type = match (true) {
            $event instanceof StopDelivered => MapUpdateType::StopDelivered,
            $event instanceof StopExceptionReported => MapUpdateType::StopException,
            $event instanceof RouteStarted => MapUpdateType::RouteStarted,
            $event instanceof RouteCompleted => MapUpdateType::RouteCompleted,
            $event instanceof RouteCancelled => MapUpdateType::RouteCancelled,
            $event instanceof RouteOptimized => MapUpdateType::RouteOptimized,
            $event instanceof RouteAssigned => MapUpdateType::RouteAssigned,
            $event instanceof EtaChanged => MapUpdateType::EtaChanged,
            $event instanceof DeviationDetected => MapUpdateType::DeviationDetected,
            $event instanceof DeviationEnded => MapUpdateType::DeviationEnded,
            default => throw new \InvalidArgumentException(sprintf(
                'Unsupported event type: %s',
                $event::class,
            )),
        };

        return [new MapUpdate(
            type: $type,
            routePublicId: $event->getRoutePublicId(),
            data: [],
            occurredAt: $event->getOccurredAt(),
        )];
    }

    public function projectVehiclePosition(VehiclePositionReceived $event): VehiclePosition
    {
        return new VehiclePosition(
            vehiclePublicId: $event->vehiclePublicId,
            lat: $event->latitude,
            lng: $event->longitude,
            speed: $event->speed,
            course: $event->course,
            deviceTime: $event->deviceTime,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function publishFromEvent(
        MapProjectableEventInterface $event,
        MapUpdateType $type,
        array $data = [],
    ): void {
        $update = new MapUpdate(
            type: $type,
            routePublicId: $event->getRoutePublicId(),
            data: $data,
            occurredAt: $event->getOccurredAt(),
        );

        try {
            $this->publisher->publishRouteUpdate($update);
        } catch (\Throwable $e) {
            $this->logger->error('MapEventProjector: failed to publish route update', [
                'type' => $type->value,
                'routePublicId' => $event->getRoutePublicId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
