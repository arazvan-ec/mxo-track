<?php

declare(strict_types=1);

namespace App\Routing;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * OSRM adapter for the RoutingEngine port.
 *
 * Encapsulates OSRM-specific details: [longitude, latitude] coordinate order,
 * /route/v1/driving/ endpoint, distance in meters → km conversion.
 */
final class OsrmRoutingEngine implements RoutingEngineInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $osrmUrl,
    ) {
    }

    public function route(float $fromLat, float $fromLng, float $toLat, float $toLng): RouteResult
    {
        $coords = sprintf('%f,%f;%f,%f', $fromLng, $fromLat, $toLng, $toLat);
        $url = sprintf('%s/route/v1/driving/%s?overview=false', $this->osrmUrl, $coords);

        try {
            $response = $this->httpClient->request('GET', $url, ['timeout' => 10]);
            $data = $response->toArray();
        } catch (\Throwable $e) {
            $this->logger->error('OSRM route request failed.', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return new RouteResult(0.0, 0.0);
        }

        if (($data['code'] ?? '') !== 'Ok' || empty($data['routes'])) {
            return new RouteResult(0.0, 0.0);
        }

        $route = $data['routes'][0];

        return new RouteResult(
            distanceKm: ($route['distance'] ?? 0) / 1000.0,
            durationSeconds: (float) ($route['duration'] ?? 0),
        );
    }

    public function routeWithWaypoints(array $waypoints): MultiWaypointRouteResult
    {
        if (\count($waypoints) < 2) {
            return new MultiWaypointRouteResult(0.0, 0.0, []);
        }

        $coordParts = [];
        foreach ($waypoints as $wp) {
            $coordParts[] = sprintf('%f,%f', $wp->longitude, $wp->latitude);
        }

        $url = sprintf(
            '%s/route/v1/driving/%s?overview=false&steps=false',
            $this->osrmUrl,
            implode(';', $coordParts),
        );

        try {
            $response = $this->httpClient->request('GET', $url, ['timeout' => 15]);
            $data = $response->toArray();
        } catch (\Throwable $e) {
            $this->logger->error('OSRM waypoint route request failed.', [
                'url' => $url,
                'waypointCount' => \count($waypoints),
                'error' => $e->getMessage(),
            ]);

            return new MultiWaypointRouteResult(0.0, 0.0, []);
        }

        if (($data['code'] ?? '') !== 'Ok' || empty($data['routes'])) {
            return new MultiWaypointRouteResult(0.0, 0.0, []);
        }

        $route = $data['routes'][0];
        $legs = [];

        foreach ($route['legs'] ?? [] as $leg) {
            $legs[] = new RouteResult(
                distanceKm: ($leg['distance'] ?? 0) / 1000.0,
                durationSeconds: (float) ($leg['duration'] ?? 0),
            );
        }

        return new MultiWaypointRouteResult(
            totalDistanceKm: ($route['distance'] ?? 0) / 1000.0,
            totalDurationSeconds: (float) ($route['duration'] ?? 0),
            legs: $legs,
        );
    }
}
