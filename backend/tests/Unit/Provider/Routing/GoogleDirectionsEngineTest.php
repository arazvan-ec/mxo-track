<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider\Routing;

use App\Provider\ProviderUnavailableException;
use App\Provider\Routing\GoogleDirectionsConfig;
use App\Provider\Routing\GoogleDirectionsEngine;
use App\Routing\Coordinate;
use App\Routing\MultiWaypointRouteResult;
use App\Routing\RouteResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GoogleDirectionsEngineTest extends TestCase
{
    private const API_KEY = 'test-api-key-123';

    #[Test]
    public function route_calls_google_api_with_correct_params(): void
    {
        $requestHistory = [];
        $mockResponse = new MockResponse(json_encode($this->singleLegResponse(5000, 300)), [
            'http_code' => 200,
        ]);

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requestHistory, $mockResponse): MockResponse {
            $requestHistory[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return $mockResponse;
        });

        $config = new GoogleDirectionsConfig(apiKey: self::API_KEY, region: 'es');
        $engine = new GoogleDirectionsEngine($httpClient, $config);

        $engine->route(40.4168, -3.7038, 41.3851, 2.1734);

        self::assertCount(1, $requestHistory);
        self::assertSame('GET', $requestHistory[0]['method']);

        $url = $requestHistory[0]['url'];
        self::assertStringContainsString('maps.googleapis.com/maps/api/directions/json', $url);
        self::assertStringContainsString('origin=40.4168%2C-3.7038', $url);
        self::assertStringContainsString('destination=41.3851%2C2.1734', $url);
        self::assertStringContainsString('key=test-api-key-123', $url);
        self::assertStringContainsString('region=es', $url);
    }

    #[Test]
    public function route_parses_distance_and_duration_correctly(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode($this->singleLegResponse(15000, 900)), [
                'http_code' => 200,
            ]),
        ]);

        $config = new GoogleDirectionsConfig(apiKey: self::API_KEY);
        $engine = new GoogleDirectionsEngine($httpClient, $config);

        $result = $engine->route(40.4168, -3.7038, 41.3851, 2.1734);

        self::assertInstanceOf(RouteResult::class, $result);
        self::assertEqualsWithDelta(15.0, $result->distanceKm, 0.001); // 15000m → 15km
        self::assertEqualsWithDelta(900.0, $result->durationSeconds, 0.001);
    }

    #[Test]
    public function route_includes_avoid_tolls_when_configured(): void
    {
        $requestHistory = [];
        $mockResponse = new MockResponse(json_encode($this->singleLegResponse(1000, 60)), [
            'http_code' => 200,
        ]);

        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requestHistory, $mockResponse): MockResponse {
            $requestHistory[] = $url;

            return $mockResponse;
        });

        $config = new GoogleDirectionsConfig(apiKey: self::API_KEY, avoidTolls: true);
        $engine = new GoogleDirectionsEngine($httpClient, $config);

        $engine->route(40.0, -3.0, 41.0, 2.0);

        self::assertStringContainsString('avoid=tolls', $requestHistory[0]);
    }

    #[Test]
    public function route_does_not_include_avoid_when_tolls_not_avoided(): void
    {
        $requestHistory = [];
        $mockResponse = new MockResponse(json_encode($this->singleLegResponse(1000, 60)), [
            'http_code' => 200,
        ]);

        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requestHistory, $mockResponse): MockResponse {
            $requestHistory[] = $url;

            return $mockResponse;
        });

        $config = new GoogleDirectionsConfig(apiKey: self::API_KEY, avoidTolls: false);
        $engine = new GoogleDirectionsEngine($httpClient, $config);

        $engine->route(40.0, -3.0, 41.0, 2.0);

        self::assertStringNotContainsString('avoid=', $requestHistory[0]);
    }

    #[Test]
    public function route_with_waypoints_handles_multiple_legs(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode($this->multiLegResponse([
                ['distance' => 5000, 'duration' => 300],
                ['distance' => 8000, 'duration' => 480],
                ['distance' => 3000, 'duration' => 180],
            ])), ['http_code' => 200]),
        ]);

        $config = new GoogleDirectionsConfig(apiKey: self::API_KEY);
        $engine = new GoogleDirectionsEngine($httpClient, $config);

        $waypoints = [
            new Coordinate(40.4168, -3.7038),
            new Coordinate(40.4530, -3.6883),
            new Coordinate(40.4700, -3.6700),
            new Coordinate(41.3851, 2.1734),
        ];

        $result = $engine->routeWithWaypoints($waypoints);

        self::assertInstanceOf(MultiWaypointRouteResult::class, $result);
        self::assertEqualsWithDelta(16.0, $result->totalDistanceKm, 0.001); // (5+8+3)km
        self::assertEqualsWithDelta(960.0, $result->totalDurationSeconds, 0.001); // 300+480+180
        self::assertCount(3, $result->legs);

        self::assertEqualsWithDelta(5.0, $result->legs[0]->distanceKm, 0.001);
        self::assertEqualsWithDelta(300.0, $result->legs[0]->durationSeconds, 0.001);
        self::assertEqualsWithDelta(8.0, $result->legs[1]->distanceKm, 0.001);
        self::assertEqualsWithDelta(3.0, $result->legs[2]->distanceKm, 0.001);
    }

    #[Test]
    public function route_with_waypoints_sends_intermediate_waypoints_in_url(): void
    {
        $requestHistory = [];
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requestHistory): MockResponse {
            $requestHistory[] = $url;

            return new MockResponse(json_encode($this->multiLegResponse([
                ['distance' => 1000, 'duration' => 60],
                ['distance' => 2000, 'duration' => 120],
            ])), ['http_code' => 200]);
        });

        $config = new GoogleDirectionsConfig(apiKey: self::API_KEY);
        $engine = new GoogleDirectionsEngine($httpClient, $config);

        $waypoints = [
            new Coordinate(40.0, -3.0),
            new Coordinate(40.5, -3.5),
            new Coordinate(41.0, 2.0),
        ];

        $engine->routeWithWaypoints($waypoints);

        self::assertCount(1, $requestHistory);
        $url = $requestHistory[0];
        self::assertStringContainsString('origin=40%2C-3', $url);
        self::assertStringContainsString('destination=41%2C2', $url);
        // Intermediate waypoints use via: prefix
        self::assertStringContainsString('waypoints=via:40.5%2C-3.5', $url);
    }

    #[Test]
    public function route_throws_on_http_transport_error(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('', ['error' => 'Connection refused']),
        ]);

        $config = new GoogleDirectionsConfig(apiKey: self::API_KEY);
        $engine = new GoogleDirectionsEngine($httpClient, $config);

        $this->expectException(ProviderUnavailableException::class);
        $this->expectExceptionMessageMatches('/Google Directions API/');

        $engine->route(40.0, -3.0, 41.0, 2.0);
    }

    #[Test]
    public function route_throws_on_api_error_status(): void
    {
        $response = [
            'status' => 'ZERO_RESULTS',
            'routes' => [],
            'geocoded_waypoints' => [],
        ];

        $httpClient = new MockHttpClient([
            new MockResponse(json_encode($response), ['http_code' => 200]),
        ]);

        $config = new GoogleDirectionsConfig(apiKey: self::API_KEY);
        $engine = new GoogleDirectionsEngine($httpClient, $config);

        $this->expectException(ProviderUnavailableException::class);
        $this->expectExceptionMessageMatches('/ZERO_RESULTS/');

        $engine->route(40.0, -3.0, 41.0, 2.0);
    }

    #[Test]
    public function route_throws_on_http_500_error(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('Internal Server Error', ['http_code' => 500]),
        ]);

        $config = new GoogleDirectionsConfig(apiKey: self::API_KEY);
        $engine = new GoogleDirectionsEngine($httpClient, $config);

        $this->expectException(ProviderUnavailableException::class);

        $engine->route(40.0, -3.0, 41.0, 2.0);
    }

    #[Test]
    public function route_with_waypoints_with_less_than_two_points_returns_empty(): void
    {
        $httpClient = new MockHttpClient([]);

        $config = new GoogleDirectionsConfig(apiKey: self::API_KEY);
        $engine = new GoogleDirectionsEngine($httpClient, $config);

        $result = $engine->routeWithWaypoints([new Coordinate(40.0, -3.0)]);

        self::assertInstanceOf(MultiWaypointRouteResult::class, $result);
        self::assertEqualsWithDelta(0.0, $result->totalDistanceKm, 0.001);
        self::assertEqualsWithDelta(0.0, $result->totalDurationSeconds, 0.001);
        self::assertCount(0, $result->legs);
    }

    /**
     * @return array<string, mixed>
     */
    private function singleLegResponse(int $distanceMeters, int $durationSeconds): array
    {
        return [
            'status' => 'OK',
            'routes' => [
                [
                    'legs' => [
                        [
                            'distance' => ['value' => $distanceMeters, 'text' => ''],
                            'duration' => ['value' => $durationSeconds, 'text' => ''],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param list<array{distance: int, duration: int}> $legs
     *
     * @return array<string, mixed>
     */
    private function multiLegResponse(array $legs): array
    {
        $responsLegs = [];
        foreach ($legs as $leg) {
            $responsLegs[] = [
                'distance' => ['value' => $leg['distance'], 'text' => ''],
                'duration' => ['value' => $leg['duration'], 'text' => ''],
            ];
        }

        return [
            'status' => 'OK',
            'routes' => [
                [
                    'legs' => $responsLegs,
                ],
            ],
        ];
    }
}
