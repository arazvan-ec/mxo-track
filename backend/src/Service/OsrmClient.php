<?php

declare(strict_types=1);

namespace App\Service;

use App\Routing\Coordinate;
use App\Routing\OsrmRoutingEngine;

/**
 * @deprecated Use App\Routing\RoutingEngineInterface instead.
 */
final class OsrmClient
{
    public function __construct(
        private readonly OsrmRoutingEngine $engine,
    ) {
    }

    /**
     * @deprecated Use RoutingEngineInterface::route() instead.
     *
     * @return array{distanceKm: float, durationSeconds: float}
     */
    public function getRoute(float $fromLat, float $fromLng, float $toLat, float $toLng): array
    {
        $result = $this->engine->route($fromLat, $fromLng, $toLat, $toLng);

        return [
            'distanceKm' => $result->distanceKm,
            'durationSeconds' => $result->durationSeconds,
        ];
    }

    /**
     * @deprecated Use RoutingEngineInterface::routeWithWaypoints() instead.
     *
     * @param list<array{lat: float, lng: float}> $waypoints At least 2 points
     * @return array{
     *     totalDistanceKm: float,
     *     totalDurationSeconds: float,
     *     legs: list<array{distanceKm: float, durationSeconds: float}>,
     * }
     */
    public function getRouteWithWaypoints(array $waypoints): array
    {
        $coordinates = array_map(
            static fn(array $wp) => new Coordinate($wp['lat'], $wp['lng']),
            $waypoints,
        );

        $result = $this->engine->routeWithWaypoints($coordinates);

        $legs = array_map(
            static fn($leg) => ['distanceKm' => $leg->distanceKm, 'durationSeconds' => $leg->durationSeconds],
            $result->legs,
        );

        return [
            'totalDistanceKm' => $result->totalDistanceKm,
            'totalDurationSeconds' => $result->totalDurationSeconds,
            'legs' => $legs,
        ];
    }
}
