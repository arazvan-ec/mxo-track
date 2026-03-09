<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Dispatched after position ingestion to trigger asynchronous anomaly detection.
 */
final readonly class FleetAnomalyCheckMessage
{
    public function __construct(
        private int $vehicleId,
        private int $routeId,
    ) {}

    public function getVehicleId(): int
    {
        return $this->vehicleId;
    }

    public function getRouteId(): int
    {
        return $this->routeId;
    }
}
