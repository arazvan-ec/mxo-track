<?php

declare(strict_types=1);

namespace App\Routing;

/**
 * Port interface for road routing calculations.
 *
 * Abstracts the routing engine (OSRM, Google Maps, Graphhopper, etc.)
 * behind a domain-oriented contract.
 */
interface RoutingEngineInterface
{
    /**
     * Calculates road distance and duration between two points.
     */
    public function route(float $fromLat, float $fromLng, float $toLat, float $toLng): RouteResult;

    /**
     * Calculates road distance and duration through multiple consecutive waypoints.
     *
     * @param list<Coordinate> $waypoints At least 2 points
     */
    public function routeWithWaypoints(array $waypoints): MultiWaypointRouteResult;
}
