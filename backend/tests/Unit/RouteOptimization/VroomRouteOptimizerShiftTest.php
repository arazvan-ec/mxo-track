<?php

declare(strict_types=1);

namespace App\Tests\Unit\RouteOptimization;

use App\RouteOptimization\OptimizableJob;
use App\RouteOptimization\OptimizableVehicle;
use App\RouteOptimization\VroomRouteOptimizer;
use App\Service\OptimizationLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(VroomRouteOptimizer::class)]
final class VroomRouteOptimizerShiftTest extends TestCase
{
    private const string VROOM_URL = 'http://vroom:3000';

    private const array VALID_VROOM_RESPONSE = [
        'code' => 0,
        'routes' => [
            [
                'vehicle' => 0,
                'steps' => [
                    ['type' => 'start'],
                    ['type' => 'job', 'id' => 0, 'arrival' => 0, 'service' => 300],
                    ['type' => 'end'],
                ],
                'distance' => 1000,
                'duration' => 600,
            ],
        ],
        'unassigned' => [],
        'summary' => ['distance' => 1000, 'duration' => 600],
    ];

    #[Test]
    public function vehicleWithShiftTimesSendsTimeWindowToVroom(): void
    {
        $capturedBody = null;

        $mockResponse = new MockResponse(json_encode(self::VALID_VROOM_RESPONSE));
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedBody, $mockResponse): MockResponse {
            $capturedBody = json_decode($options['body'], true);

            return $mockResponse;
        });

        $optimizationLogger = $this->createMock(OptimizationLogger::class);

        $optimizer = new VroomRouteOptimizer(
            httpClient: $httpClient,
            logger: new NullLogger(),
            optimizationLogger: $optimizationLogger,
            vroomUrl: self::VROOM_URL,
        );

        $vehicle = new OptimizableVehicle(
            id: 0,
            startLatitude: 40.0,
            startLongitude: -3.0,
            shiftStartSeconds: 28800,  // 08:00
            shiftEndSeconds: 64800,    // 18:00
        );

        $job = new OptimizableJob(
            id: 0,
            latitude: 40.5,
            longitude: -3.5,
            serviceTimeSeconds: 300,
        );

        $optimizer->optimize([$vehicle], [$job]);

        self::assertNotNull($capturedBody, 'HTTP request should have been made to VROOM');
        self::assertArrayHasKey('vehicles', $capturedBody);
        self::assertCount(1, $capturedBody['vehicles']);

        $vroomVehicle = $capturedBody['vehicles'][0];
        self::assertArrayHasKey('time_window', $vroomVehicle, 'Vehicle with shift times must include time_window in VROOM payload');
        self::assertSame([28800, 64800], $vroomVehicle['time_window']);
    }

    #[Test]
    public function vehicleWithoutShiftTimesOmitsTimeWindowFromVroom(): void
    {
        $capturedBody = null;

        $mockResponse = new MockResponse(json_encode(self::VALID_VROOM_RESPONSE));
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedBody, $mockResponse): MockResponse {
            $capturedBody = json_decode($options['body'], true);

            return $mockResponse;
        });

        $optimizationLogger = $this->createMock(OptimizationLogger::class);

        $optimizer = new VroomRouteOptimizer(
            httpClient: $httpClient,
            logger: new NullLogger(),
            optimizationLogger: $optimizationLogger,
            vroomUrl: self::VROOM_URL,
        );

        $vehicle = new OptimizableVehicle(
            id: 0,
            startLatitude: 40.0,
            startLongitude: -3.0,
        );

        $job = new OptimizableJob(
            id: 0,
            latitude: 40.5,
            longitude: -3.5,
            serviceTimeSeconds: 300,
        );

        $optimizer->optimize([$vehicle], [$job]);

        self::assertNotNull($capturedBody, 'HTTP request should have been made to VROOM');
        self::assertArrayHasKey('vehicles', $capturedBody);
        self::assertCount(1, $capturedBody['vehicles']);

        $vroomVehicle = $capturedBody['vehicles'][0];
        self::assertArrayNotHasKey('time_window', $vroomVehicle, 'Vehicle without shift times must NOT include time_window in VROOM payload');
    }
}
