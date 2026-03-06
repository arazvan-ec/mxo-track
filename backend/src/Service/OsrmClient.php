<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client for OSRM routing engine. Provides real road distances and durations.
 *
 * OSRM uses [longitude, latitude] coordinate order in URLs.
 * Returns distances in meters and durations in seconds.
 */
final class OsrmClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $osrmUrl,
    ) {}

    /**
     * Gets the road distance (km) and duration (seconds) between two points.
     *
     * @return array{distanceKm: float, durationSeconds: float}
     */
    public function getRoute(float $fromLat, float $fromLng, float $toLat, float $toLng): array
    {
        $coords = sprintf('%f,%f;%f,%f', $fromLng, $fromLat, $toLng, $toLat);
        $url = sprintf('%s/route/v1/driving/%s?overview=false', $this->osrmUrl, $coords);

        $response = $this->httpClient->request('GET', $url, ['timeout' => 10]);
        $data = $response->toArray();

        if (($data['code'] ?? '') !== 'Ok' || empty($data['routes'])) {
            return ['distanceKm' => 0.0, 'durationSeconds' => 0.0];
        }

        $route = $data['routes'][0];

        return [
            'distanceKm' => ($route['distance'] ?? 0) / 1000.0,
            'durationSeconds' => (float) ($route['duration'] ?? 0),
        ];
    }

    /**
     * Gets road distances and durations for multiple consecutive waypoints.
     * Uses OSRM route service with all waypoints in one request.
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
        if (\count($waypoints) < 2) {
            return ['totalDistanceKm' => 0.0, 'totalDurationSeconds' => 0.0, 'legs' => []];
        }

        $coordParts = [];
        foreach ($waypoints as $wp) {
            $coordParts[] = sprintf('%f,%f', $wp['lng'], $wp['lat']);
        }

        $url = sprintf(
            '%s/route/v1/driving/%s?overview=false&steps=false',
            $this->osrmUrl,
            implode(';', $coordParts),
        );

        $response = $this->httpClient->request('GET', $url, ['timeout' => 15]);
        $data = $response->toArray();

        if (($data['code'] ?? '') !== 'Ok' || empty($data['routes'])) {
            return ['totalDistanceKm' => 0.0, 'totalDurationSeconds' => 0.0, 'legs' => []];
        }

        $route = $data['routes'][0];
        $legs = [];

        foreach ($route['legs'] ?? [] as $leg) {
            $legs[] = [
                'distanceKm' => ($leg['distance'] ?? 0) / 1000.0,
                'durationSeconds' => (float) ($leg['duration'] ?? 0),
            ];
        }

        return [
            'totalDistanceKm' => ($route['distance'] ?? 0) / 1000.0,
            'totalDurationSeconds' => (float) ($route['duration'] ?? 0),
            'legs' => $legs,
        ];
    }
}
