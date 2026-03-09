<?php

declare(strict_types=1);

namespace App\RouteOptimization;

/**
 * An optimized route assigned to a single vehicle.
 */
final readonly class OptimizedRoute
{
    /**
     * @param list<OptimizedStep> $steps Ordered steps for this route
     */
    public function __construct(
        public int|string $vehicleId,
        public array $steps,
        public int $distanceMeters = 0,
        public int $durationSeconds = 0,
    ) {
    }
}
