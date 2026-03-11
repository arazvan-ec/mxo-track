<?php

declare(strict_types=1);

namespace App\Provider\Routing;

use App\Routing\Coordinate;
use App\Routing\MultiWaypointRouteResult;
use App\Routing\RouteResult;
use App\Routing\RoutingEngineInterface;

final class HaversineEngine implements RoutingEngineInterface
{
    private const EARTH_RADIUS_KM = 6371.0;

    public function __construct(private readonly HaversineConfig $config)
    {
    }

    public function route(float $fromLat, float $fromLng, float $toLat, float $toLng): RouteResult
    {
        $distanceKm = $this->haversineDistance($fromLat, $fromLng, $toLat, $toLng) * $this->config->correctionFactor;
        $durationSeconds = $this->config->averageSpeedKmh > 0
            ? ($distanceKm / $this->config->averageSpeedKmh) * 3600.0
            : 0.0;

        return new RouteResult($distanceKm, $durationSeconds);
    }

    /**
     * @param list<Coordinate> $waypoints
     */
    public function routeWithWaypoints(array $waypoints): MultiWaypointRouteResult
    {
        if (\count($waypoints) < 2) {
            return new MultiWaypointRouteResult(0.0, 0.0, []);
        }

        $legs = [];
        $totalDistance = 0.0;
        $totalDuration = 0.0;

        for ($i = 0, $count = \count($waypoints) - 1; $i < $count; $i++) {
            $leg = $this->route(
                $waypoints[$i]->latitude,
                $waypoints[$i]->longitude,
                $waypoints[$i + 1]->latitude,
                $waypoints[$i + 1]->longitude,
            );
            $legs[] = $leg;
            $totalDistance += $leg->distanceKm;
            $totalDuration += $leg->durationSeconds;
        }

        return new MultiWaypointRouteResult($totalDistance, $totalDuration, $legs);
    }

    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_KM * $c;
    }
}
