<?php

declare(strict_types=1);

namespace App\RouteOptimization;

/**
 * Domain-neutral representation of a vehicle for route optimization.
 */
final readonly class OptimizableVehicle
{
    /**
     * @param list<string> $skills Vehicle capabilities (e.g. "refrigerated", "heavy_load")
     */
    public function __construct(
        public int|string $id,
        public ?float $startLatitude = null,
        public ?float $startLongitude = null,
        public ?float $endLatitude = null,
        public ?float $endLongitude = null,
        public ?float $maxWeightKg = null,
        public ?float $maxVolumeM3 = null,
        public ?int $maxParcels = null,
        public ?int $maxTasks = null,
        public array $skills = [],
    ) {
    }
}
