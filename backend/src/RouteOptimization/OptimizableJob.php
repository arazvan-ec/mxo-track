<?php

declare(strict_types=1);

namespace App\RouteOptimization;

/**
 * Domain-neutral representation of a delivery job for route optimization.
 */
final readonly class OptimizableJob
{
    /**
     * @param list<array{start: int, end: int}> $timeWindows Delivery windows as seconds-of-day pairs
     * @param list<string> $requiredSkills Required vehicle capabilities
     */
    public function __construct(
        public int|string $id,
        public float $latitude,
        public float $longitude,
        public int $serviceTimeSeconds = 300,
        public float $weightKg = 0.0,
        public float $volumeM3 = 0.0,
        public int $parcels = 1,
        public int $priority = 0,
        public array $timeWindows = [],
        public array $requiredSkills = [],
    ) {
    }
}
