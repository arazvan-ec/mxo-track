<?php

declare(strict_types=1);

namespace App\Tests\Unit\View;

use App\Domain\Route\Model\RouteSnapshot;
use App\Domain\Route\Repository\RouteSnapshotRepositoryInterface;
use App\Domain\Route\Service\RouteMapProjection;
use App\Domain\Route\Model\Route;
use App\Enum\RouteStatus;
use App\View\MapViewData;
use App\View\MapViewOptions;
use App\View\RouteViewService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RouteViewService::class)]
final class RouteViewServiceTest extends TestCase
{
    private RouteViewService $service;
    private RouteSnapshotRepositoryInterface $snapshotRepo;

    protected function setUp(): void
    {
        $this->snapshotRepo = $this->createMock(RouteSnapshotRepositoryInterface::class);
        $projection = new RouteMapProjection($this->snapshotRepo);
        $this->service = new RouteViewService($projection, $this->snapshotRepo);
    }

    private function createSnapshotWithStops(Route $route): RouteSnapshot
    {
        $snapshot = new RouteSnapshot($route);
        $snapshot->setPolyline('encoded_polyline');
        $snapshot->setDistanceBeforeKm(50.0);
        $snapshot->setDistanceAfterKm(35.0);
        $snapshot->setSavingsPercent(30.0);
        $snapshot->setDrivingTimeMinutes(60);
        $snapshot->setDeliveryTimeMinutes(25);
        $snapshot->setTotalTimeMinutes(85);
        $snapshot->setStopStates([
            ['publicId' => 'stop1', 'sequence' => 0, 'address' => 'Origin', 'recipientName' => null, 'lat' => 40.0, 'lng' => -3.0, 'isOrigin' => true, 'status' => 'PENDING'],
            ['publicId' => 'stop2', 'sequence' => 1, 'address' => 'Stop A', 'recipientName' => 'Juan', 'lat' => 40.1, 'lng' => -3.1, 'isOrigin' => false, 'status' => 'DELIVERED', 'deliveredAt' => '2026-03-13T10:00:00+00:00'],
            ['publicId' => 'stop3', 'sequence' => 2, 'address' => 'Stop B', 'recipientName' => 'Maria', 'lat' => 40.2, 'lng' => -3.2, 'isOrigin' => false, 'status' => 'PENDING'],
        ]);
        $snapshot->setOriginalStopOrder([
            ['sequence' => 0, 'address' => 'Origin'],
            ['sequence' => 1, 'address' => 'Stop B'],
            ['sequence' => 2, 'address' => 'Stop A'],
        ]);
        $snapshot->setCapacityValidation(['valid' => true, 'totalWeightKg' => 120.5]);

        return $snapshot;
    }

    #[Test]
    public function buildSingleRouteViewReturnsMapViewData(): void
    {
        $route = new Route('Test Route');

        $snapshot = $this->createSnapshotWithStops($route);
        $this->snapshotRepo->method('findByRoute')->willReturn($snapshot);

        $result = $this->service->buildSingleRouteView($route, 'ROLE_ADMIN');

        self::assertInstanceOf(MapViewData::class, $result);
        self::assertCount(1, $result->routes);

        $routeView = $result->routes[0];
        self::assertSame('Test Route', $routeView->name);
        self::assertSame('encoded_polyline', $routeView->polyline);
        self::assertCount(3, $routeView->stops);
        self::assertSame('DELIVERED', $routeView->stops[1]->status);
        self::assertSame('2026-03-13T10:00:00+00:00', $routeView->stops[1]->deliveredAt);
    }

    #[Test]
    public function adminSeesAllFields(): void
    {
        $route = new Route('Test Route');
        $snapshot = $this->createSnapshotWithStops($route);
        $this->snapshotRepo->method('findByRoute')->willReturn($snapshot);

        $options = new MapViewOptions(
            showOptimizationMetrics: true,
            showTimingBreakdown: true,
            showCapacityValidation: true,
            showOriginalOrder: true,
        );

        $result = $this->service->buildSingleRouteView($route, 'ROLE_ADMIN', $options);
        $routeView = $result->routes[0];

        self::assertNotNull($routeView->metrics);
        self::assertSame(50.0, $routeView->metrics['distanceBeforeKm']);
        self::assertNotNull($routeView->timing);
        self::assertSame(85, $routeView->timing['totalTimeMinutes']);
        self::assertNotNull($routeView->validation);
        self::assertNotNull($routeView->originalStops);
    }

    #[Test]
    public function customerDoesNotSeeMetrics(): void
    {
        $route = new Route('Test Route');
        $snapshot = $this->createSnapshotWithStops($route);
        $this->snapshotRepo->method('findByRoute')->willReturn($snapshot);

        $options = new MapViewOptions(
            showOptimizationMetrics: true,
            showTimingBreakdown: true,
            showCapacityValidation: true,
            showOriginalOrder: true,
        );

        $result = $this->service->buildSingleRouteView($route, 'ROLE_CUSTOMER', $options);
        $routeView = $result->routes[0];

        // Customer should NOT see admin-only fields
        self::assertNull($routeView->metrics);
        self::assertNull($routeView->timing);
        self::assertNull($routeView->validation);
        self::assertNull($routeView->originalStops);

        // But still sees polyline and stops
        self::assertSame('encoded_polyline', $routeView->polyline);
        self::assertCount(3, $routeView->stops);
    }

    #[Test]
    public function driverDoesNotSeeMetrics(): void
    {
        $route = new Route('Test Route');
        $snapshot = $this->createSnapshotWithStops($route);
        $this->snapshotRepo->method('findByRoute')->willReturn($snapshot);

        $options = new MapViewOptions(showOptimizationMetrics: true);
        $result = $this->service->buildSingleRouteView($route, 'ROLE_DRIVER', $options);
        $routeView = $result->routes[0];

        self::assertNull($routeView->metrics);
        self::assertNotNull($routeView->polyline);
    }

    #[Test]
    public function buildSingleRouteViewWithMissingSnapshotReturnsBasicView(): void
    {
        $route = new Route('Empty Route');
        $this->snapshotRepo->method('findByRoute')->willReturn(null);

        $result = $this->service->buildSingleRouteView($route, 'ROLE_ADMIN');

        self::assertInstanceOf(MapViewData::class, $result);
        self::assertCount(1, $result->routes);
        self::assertSame('Empty Route', $result->routes[0]->name);
        self::assertNull($result->routes[0]->polyline);
        self::assertSame([], $result->routes[0]->stops);
    }

    #[Test]
    public function buildMultiRouteViewCombinesRoutes(): void
    {
        $route1 = new Route('Route A');
        $route2 = new Route('Route B');

        $snapshot1 = new RouteSnapshot($route1);
        $snapshot1->setDistanceBeforeKm(30.0);
        $snapshot1->setDistanceAfterKm(20.0);
        $snapshot1->setSavingsPercent(33.3);
        $snapshot1->setStopStates([]);

        $snapshot2 = new RouteSnapshot($route2);
        $snapshot2->setDistanceBeforeKm(40.0);
        $snapshot2->setDistanceAfterKm(25.0);
        $snapshot2->setSavingsPercent(37.5);
        $snapshot2->setStopStates([]);

        $this->snapshotRepo->method('findByRoute')
            ->willReturnCallback(function (Route $route) use ($route1, $route2, $snapshot1, $snapshot2) {
                if ($route === $route1) {
                    return $snapshot1;
                }
                if ($route === $route2) {
                    return $snapshot2;
                }

                return null;
            });

        // Multi-route uses findByRoutes for bulk query
        $this->snapshotRepo->method('findByRoutes')->willReturn([]);

        $options = new MapViewOptions(showOptimizationMetrics: true);
        $result = $this->service->buildMultiRouteView([$route1, $route2], 'ROLE_ADMIN', $options);

        self::assertCount(2, $result->routes);
        self::assertSame('Route A', $result->routes[0]->name);
        self::assertSame('Route B', $result->routes[1]->name);

        // Different colors
        self::assertNotSame($result->routes[0]->color, $result->routes[1]->color);
    }

    #[Test]
    public function mercureTopicIncludesRoutePublicId(): void
    {
        $route = new Route('Test Route');
        $snapshot = new RouteSnapshot($route);
        $snapshot->setStopStates([]);
        $this->snapshotRepo->method('findByRoute')->willReturn($snapshot);

        $result = $this->service->buildSingleRouteView($route, 'ROLE_CUSTOMER');

        self::assertNotNull($result->mercureTopic);
        self::assertStringContainsString('/view/customer', $result->mercureTopic);
    }
}
