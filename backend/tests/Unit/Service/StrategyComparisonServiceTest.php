<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Customer;
use App\Entity\CustomerLocation;
use App\Entity\Vehicle;
use App\Domain\Shipment\Model\Shipment;
use App\Enum\ShipmentPriority;
use App\Provider\ProviderFactoryRegistry;
use App\RouteOptimization\OptimizationResult;
use App\RouteOptimization\OptimizedRoute;
use App\RouteOptimization\OptimizedStep;
use App\RouteOptimization\RouteOptimizerInterface;
use App\Service\OptimizationLogger;
use App\Service\RouteBuilder;
use App\Service\StrategyComparisonService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(StrategyComparisonService::class)]
final class StrategyComparisonServiceTest extends TestCase
{
    #[Test]
    public function compare_returns_results_for_each_optimizer(): void
    {
        $registry = $this->createMock(ProviderFactoryRegistry::class);
        $routeBuilder = $this->createMock(RouteBuilder::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $logger = $this->createMock(OptimizationLogger::class);

        // Registry returns 2 optimizer names
        $registry->method('getAvailableProviders')
            ->willReturn(['route_optimizer' => ['vroom', 'greedy']]);

        // Mock the optimizer resolution
        $vroomOptimizer = $this->createMock(RouteOptimizerInterface::class);
        $greedyOptimizer = $this->createMock(RouteOptimizerInterface::class);

        $registry->method('createByName')
            ->willReturnMap([
                ['vroom', $vroomOptimizer],
                ['greedy', $greedyOptimizer],
            ]);

        // RouteBuilder returns different results per optimizer
        $routeBuilder->method('buildRoutes')
            ->willReturnOnConsecutiveCalls(
                [['route' => $this->createMockRoute(10.5, 45), 'stops' => [], 'validation' => []]],
                [['route' => $this->createMockRoute(12.0, 50), 'stops' => [], 'validation' => []]],
            );

        $em->method('persist');
        $em->method('flush');

        $service = new StrategyComparisonService($registry, $routeBuilder, $em, $logger);

        $result = $service->compare(
            $this->createShipments(5),
            $this->createVehicles(2),
            $this->createMock(Customer::class),
        );

        self::assertCount(2, $result);
        self::assertSame('vroom', $result[0]['optimizer_name']);
        self::assertSame('greedy', $result[1]['optimizer_name']);
        self::assertArrayHasKey('distance_km', $result[0]);
        self::assertArrayHasKey('duration_min', $result[0]);
    }

    #[Test]
    public function compare_with_single_optimizer_returns_one_result(): void
    {
        $registry = $this->createMock(ProviderFactoryRegistry::class);
        $routeBuilder = $this->createMock(RouteBuilder::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $logger = $this->createMock(OptimizationLogger::class);

        $registry->method('getAvailableProviders')
            ->willReturn(['route_optimizer' => ['vroom']]);

        $optimizer = $this->createMock(RouteOptimizerInterface::class);
        $registry->method('createByName')->willReturn($optimizer);

        $routeBuilder->method('buildRoutes')
            ->willReturn([['route' => $this->createMockRoute(10.5, 45), 'stops' => [], 'validation' => []]]);

        $em->method('persist');
        $em->method('flush');

        $service = new StrategyComparisonService($registry, $routeBuilder, $em, $logger);

        $result = $service->compare(
            $this->createShipments(3),
            $this->createVehicles(1),
            $this->createMock(Customer::class),
        );

        self::assertCount(1, $result);
        self::assertSame('vroom', $result[0]['optimizer_name']);
    }

    private function createMockRoute(float $distanceKm, int $durationMin): object
    {
        $route = $this->createMock(\App\Domain\Route\Model\Route::class);
        $route->method('getTotalDistanceKm')->willReturn($distanceKm);
        $route->method('getEstimatedDurationMinutes')->willReturn($durationMin);

        return $route;
    }

    private function createShipments(int $count): array
    {
        $shipments = [];
        for ($i = 0; $i < $count; $i++) {
            $s = $this->createMock(Shipment::class);
            $s->method('getLatitude')->willReturn(40.0 + $i * 0.01);
            $s->method('getLongitude')->willReturn(-3.0 + $i * 0.01);
            $shipments[] = $s;
        }

        return $shipments;
    }

    private function createVehicles(int $count): array
    {
        $vehicles = [];
        for ($i = 0; $i < $count; $i++) {
            $vehicles[] = $this->createMock(Vehicle::class);
        }

        return $vehicles;
    }
}
