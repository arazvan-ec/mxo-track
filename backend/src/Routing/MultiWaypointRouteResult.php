<?php

declare(strict_types=1);

namespace App\Routing;

/**
 * Result of a multi-waypoint route calculation with per-leg breakdown.
 */
final readonly class MultiWaypointRouteResult
{
    /**
     * @param list<RouteResult> $legs Per-leg distance and duration breakdown
     * @param ?string $geometry Encoded polyline (Google format) from routing engine, if requested
     */
    public function __construct(
        public float $totalDistanceKm,
        public float $totalDurationSeconds,
        public array $legs,
        public ?string $geometry = null,
    ) {
    }
}
