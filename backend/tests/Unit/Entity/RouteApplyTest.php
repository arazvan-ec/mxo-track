<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteEvent;
use App\Enum\RouteEventType;
use App\Enum\RouteStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Route::class)]
final class RouteApplyTest extends TestCase
{
    #[Test]
    public function applyStartedTransitionsToActive(): void
    {
        $route = new Route('Test Route');
        $event = new RouteEvent($route, RouteEventType::STARTED, 'driver', payload: ['driver_user_id' => 1]);

        $route->apply($event);

        self::assertSame(RouteStatus::ACTIVE, $route->getStatus());
        self::assertNotNull($route->getStartAt());
    }

    #[Test]
    public function applyStartedOnlyFromPlanned(): void
    {
        $route = new Route('Test Route');
        // Force to ACTIVE first
        $startEvent = new RouteEvent($route, RouteEventType::STARTED, 'driver');
        $route->apply($startEvent);

        // Applying STARTED again should be a no-op
        $route->apply(new RouteEvent($route, RouteEventType::STARTED, 'driver'));

        self::assertSame(RouteStatus::ACTIVE, $route->getStatus());
    }

    #[Test]
    public function applyCompletedTransitionsToDone(): void
    {
        $route = new Route('Test Route');
        $route->apply(new RouteEvent($route, RouteEventType::STARTED, 'driver'));
        $route->apply(new RouteEvent($route, RouteEventType::COMPLETED, 'driver'));

        self::assertSame(RouteStatus::DONE, $route->getStatus());
        self::assertNotNull($route->getEndAt());
    }

    #[Test]
    public function applyCompletedOnlyFromActive(): void
    {
        $route = new Route('Test Route');
        // PLANNED → try COMPLETED → should be no-op
        $route->apply(new RouteEvent($route, RouteEventType::COMPLETED, 'driver'));

        self::assertSame(RouteStatus::PLANNED, $route->getStatus());
    }

    #[Test]
    public function applyCancelledTransitionsToCancelled(): void
    {
        $route = new Route('Test Route');
        $route->apply(new RouteEvent($route, RouteEventType::CANCELLED, 'admin', payload: ['reason' => 'No vehicles']));

        self::assertSame(RouteStatus::CANCELLED, $route->getStatus());
    }

    #[Test]
    public function applyOptimizedSetsMetrics(): void
    {
        $route = new Route('Test Route');
        $route->apply(new RouteEvent($route, RouteEventType::OPTIMIZED, 'system', payload: [
            'distance_km' => 42.5,
            'duration_minutes' => 60,
        ]));

        self::assertSame(42.5, $route->getTotalDistanceKm());
        self::assertSame(60, $route->getEstimatedDurationMinutes());
    }

    #[Test]
    public function applyReoptimizedSetsMetrics(): void
    {
        $route = new Route('Test Route');
        $route->apply(new RouteEvent($route, RouteEventType::REOPTIMIZED, 'system', payload: [
            'distance_km' => 35.0,
            'duration_minutes' => 50,
        ]));

        self::assertSame(35.0, $route->getTotalDistanceKm());
        self::assertSame(50, $route->getEstimatedDurationMinutes());
    }

    #[Test]
    public function rebuildFromEventsReproducesState(): void
    {
        $route = new Route('Test Route');

        $events = [
            new RouteEvent($route, RouteEventType::CREATED, 'system', payload: [
                'shipment_count' => 5,
                'vehicle_count' => 1,
            ]),
            new RouteEvent($route, RouteEventType::OPTIMIZED, 'system', payload: [
                'distance_km' => 42.5,
                'duration_minutes' => 60,
            ]),
            new RouteEvent($route, RouteEventType::STARTED, 'driver', payload: [
                'driver_user_id' => 1,
            ]),
        ];

        $rebuilt = Route::rebuildFromEvents('Test Route', $events);

        self::assertSame(RouteStatus::ACTIVE, $rebuilt->getStatus());
        self::assertSame(42.5, $rebuilt->getTotalDistanceKm());
        self::assertSame(60, $rebuilt->getEstimatedDurationMinutes());
        self::assertNotNull($rebuilt->getStartAt());
    }

    #[Test]
    public function rebuildFromEmptyEventsReturnsPlannedRoute(): void
    {
        $rebuilt = Route::rebuildFromEvents('Empty Route', []);

        self::assertSame(RouteStatus::PLANNED, $rebuilt->getStatus());
        self::assertSame('Empty Route', $rebuilt->getName());
    }
}
