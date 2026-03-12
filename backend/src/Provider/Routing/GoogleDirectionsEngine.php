<?php

declare(strict_types=1);

namespace App\Provider\Routing;

use App\Provider\ProviderUnavailableException;
use App\Routing\Coordinate;
use App\Routing\MultiWaypointRouteResult;
use App\Routing\RouteResult;
use App\Routing\RoutingEngineInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GoogleDirectionsEngine implements RoutingEngineInterface
{
    private const ENDPOINT = 'https://maps.googleapis.com/maps/api/directions/json';
    private const PROVIDER_TYPE = 'google_directions';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly GoogleDirectionsConfig $config,
    ) {
    }

    public function route(float $fromLat, float $fromLng, float $toLat, float $toLng): RouteResult
    {
        $query = [
            'origin' => $fromLat . ',' . $fromLng,
            'destination' => $toLat . ',' . $toLng,
            'key' => $this->config->apiKey,
            'region' => $this->config->region,
        ];

        if ($this->config->avoidTolls) {
            $query['avoid'] = 'tolls';
        }

        $data = $this->request($query);
        $leg = $data['routes'][0]['legs'][0];

        return new RouteResult(
            distanceKm: $leg['distance']['value'] / 1000.0,
            durationSeconds: (float) $leg['duration']['value'],
        );
    }

    /**
     * @param list<Coordinate> $waypoints
     */
    public function routeWithWaypoints(array $waypoints): MultiWaypointRouteResult
    {
        if (\count($waypoints) < 2) {
            return new MultiWaypointRouteResult(0.0, 0.0, []);
        }

        $origin = $waypoints[0];
        $destination = $waypoints[\count($waypoints) - 1];

        $query = [
            'origin' => $origin->latitude . ',' . $origin->longitude,
            'destination' => $destination->latitude . ',' . $destination->longitude,
            'key' => $this->config->apiKey,
            'region' => $this->config->region,
        ];

        if ($this->config->avoidTolls) {
            $query['avoid'] = 'tolls';
        }

        // Add intermediate waypoints (all except first and last)
        if (\count($waypoints) > 2) {
            $intermediates = [];
            for ($i = 1, $count = \count($waypoints) - 1; $i < $count; $i++) {
                $intermediates[] = 'via:' . $waypoints[$i]->latitude . ',' . $waypoints[$i]->longitude;
            }
            $query['waypoints'] = implode('|', $intermediates);
        }

        $data = $this->request($query);

        $legs = [];
        $totalDistance = 0.0;
        $totalDuration = 0.0;

        foreach ($data['routes'][0]['legs'] as $leg) {
            $distanceKm = $leg['distance']['value'] / 1000.0;
            $durationSeconds = (float) $leg['duration']['value'];

            $legs[] = new RouteResult($distanceKm, $durationSeconds);
            $totalDistance += $distanceKm;
            $totalDuration += $durationSeconds;
        }

        $geometry = $data['routes'][0]['overview_polyline']['points'] ?? null;

        return new MultiWaypointRouteResult($totalDistance, $totalDuration, $legs, $geometry);
    }

    /**
     * @param array<string, string> $query
     *
     * @return array<string, mixed>
     */
    private function request(array $query): array
    {
        try {
            $response = $this->httpClient->request('GET', self::ENDPOINT, [
                'query' => $query,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                throw new ProviderUnavailableException(
                    self::PROVIDER_TYPE,
                    sprintf('Google Directions API returned HTTP %d', $statusCode),
                );
            }

            /** @var array<string, mixed> $data */
            $data = $response->toArray();
        } catch (ProviderUnavailableException $e) {
            throw $e;
        } catch (TransportExceptionInterface $e) {
            throw new ProviderUnavailableException(
                self::PROVIDER_TYPE,
                'Google Directions API transport error: ' . $e->getMessage(),
                $e,
            );
        } catch (\Throwable $e) {
            throw new ProviderUnavailableException(
                self::PROVIDER_TYPE,
                'Google Directions API error: ' . $e->getMessage(),
                $e,
            );
        }

        $status = $data['status'] ?? 'UNKNOWN';
        if ($status !== 'OK') {
            throw new ProviderUnavailableException(
                self::PROVIDER_TYPE,
                sprintf('Google Directions API returned status: %s', $status),
            );
        }

        return $data;
    }
}
