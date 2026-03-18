<?php

declare(strict_types=1);

namespace App\Infrastructure\MapView\Publisher;

use App\Domain\MapView\Model\MapUpdate;
use App\Domain\MapView\Model\VehiclePosition;
use App\Domain\MapView\Publisher\MapPublisherInterface;
use App\Realtime\RealtimePublisherInterface;
use App\Realtime\SseMessage;

final readonly class MercureMapPublisher implements MapPublisherInterface
{
    public function __construct(
        private RealtimePublisherInterface $publisher,
    ) {}

    public function publishRouteUpdate(MapUpdate $update): void
    {
        $this->publisher->publish(new SseMessage(
            data: $update->toArray(),
            topics: [sprintf('/map/routes/%s/updates', $update->routePublicId)],
            private: true,
        ));
    }

    public function publishVehiclePosition(VehiclePosition $position): void
    {
        $this->publisher->publish(new SseMessage(
            data: $position->toArray(),
            topics: [sprintf('/map/vehicles/%s/position', $position->vehiclePublicId)],
            private: true,
        ));
    }
}
