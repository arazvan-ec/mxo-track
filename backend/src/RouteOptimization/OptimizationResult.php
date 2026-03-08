<?php

declare(strict_types=1);

namespace App\RouteOptimization;

/**
 * Result of a route optimization: assigned routes and unassigned jobs.
 */
final readonly class OptimizationResult
{
    /**
     * @param list<OptimizedRoute> $routes    Optimized routes per vehicle
     * @param list<int|string>     $unassignedJobIds Jobs that could not be assigned
     */
    public function __construct(
        public array $routes,
        public array $unassignedJobIds = [],
    ) {
    }
}
