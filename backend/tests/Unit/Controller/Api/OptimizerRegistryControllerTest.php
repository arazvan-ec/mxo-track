<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Api;

use App\Controller\Api\OptimizerRegistryController;
use App\Provider\ProviderFactoryRegistry;
use App\Repository\RoutePerformanceMetricRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OptimizerRegistryController::class)]
final class OptimizerRegistryControllerTest extends TestCase
{
    #[Test]
    public function returnsOptimizersWithStats(): void
    {
        $registry = $this->createMock(ProviderFactoryRegistry::class);
        $registry->method('getAvailableProviders')->willReturn([
            'route_optimizer' => ['vroom', 'osrm'],
            'gps_provider' => ['traccar'],
        ]);

        $metricsRepo = $this->createMock(RoutePerformanceMetricRepository::class);
        $metricsRepo->method('getMetricsByOptimizer')->willReturn([
            [
                'optimizer_used' => 'vroom',
                'avg_distance_km' => '45.20',
                'avg_duration_min' => '120.00',
                'route_count' => 50,
                'avg_success_rate' => '95.50',
            ],
            [
                'optimizer_used' => 'osrm',
                'avg_distance_km' => '38.10',
                'avg_duration_min' => '105.50',
                'route_count' => 30,
                'avg_success_rate' => '92.00',
            ],
        ]);

        $controller = new OptimizerRegistryController($registry, $metricsRepo);
        $response = $controller->list();

        self::assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        self::assertCount(2, $data);

        self::assertSame('vroom', $data[0]['name']);
        self::assertSame('45.20', $data[0]['stats']['avg_distance_km']);
        self::assertSame('120.00', $data[0]['stats']['avg_duration_min']);
        self::assertSame(50, $data[0]['stats']['route_count']);
        self::assertSame('95.50', $data[0]['stats']['avg_success_rate']);

        self::assertSame('osrm', $data[1]['name']);
        self::assertSame('38.10', $data[1]['stats']['avg_distance_km']);
        self::assertSame(30, $data[1]['stats']['route_count']);
    }

    #[Test]
    public function returnsNullStatsWhenNoHistoricalMetrics(): void
    {
        $registry = $this->createMock(ProviderFactoryRegistry::class);
        $registry->method('getAvailableProviders')->willReturn([
            'route_optimizer' => ['vroom'],
        ]);

        $metricsRepo = $this->createMock(RoutePerformanceMetricRepository::class);
        $metricsRepo->method('getMetricsByOptimizer')->willReturn([]);

        $controller = new OptimizerRegistryController($registry, $metricsRepo);
        $response = $controller->list();

        self::assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        self::assertCount(1, $data);
        self::assertSame('vroom', $data[0]['name']);
        self::assertNull($data[0]['stats']);
    }

    #[Test]
    public function filtersOnlyRouteOptimizerProviders(): void
    {
        $registry = $this->createMock(ProviderFactoryRegistry::class);
        $registry->method('getAvailableProviders')->willReturn([
            'gps_provider' => ['traccar'],
            'sms_notifier' => ['twilio'],
        ]);

        $metricsRepo = $this->createMock(RoutePerformanceMetricRepository::class);
        $metricsRepo->method('getMetricsByOptimizer')->willReturn([]);

        $controller = new OptimizerRegistryController($registry, $metricsRepo);
        $response = $controller->list();

        $data = json_decode($response->getContent(), true);
        self::assertCount(0, $data);
    }
}
