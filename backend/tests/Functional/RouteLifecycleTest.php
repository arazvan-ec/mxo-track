<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Domain\Route\Model\Route;
use App\Enum\RouteStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests the Route entity state machine transitions.
 *
 * While this is technically a unit test of an entity, it verifies
 * the lifecycle/state machine that is core to the business domain,
 * hence it lives in the Functional suite.
 */
#[CoversClass(Route::class)]
final class RouteLifecycleTest extends TestCase
{
    #[Test]
    public function newRouteHasPlannedStatus(): void
    {
        $route = new Route('Delivery Route Alpha');

        self::assertSame(RouteStatus::PLANNED, $route->getStatus());
        self::assertNull($route->getStartAt());
        self::assertNull($route->getEndAt());
    }

    #[Test]
    public function startTransitionsPlannedToActive(): void
    {
        $route = new Route('Delivery Route Beta');

        self::assertSame(RouteStatus::PLANNED, $route->getStatus());

        $route->start();

        self::assertSame(RouteStatus::ACTIVE, $route->getStatus());
        self::assertNotNull($route->getStartAt());
        self::assertNull($route->getEndAt());
    }

    #[Test]
    public function finishTransitionsActiveToDone(): void
    {
        $route = new Route('Delivery Route Gamma');
        $route->start();

        self::assertSame(RouteStatus::ACTIVE, $route->getStatus());

        $route->finish();

        self::assertSame(RouteStatus::DONE, $route->getStatus());
        self::assertNotNull($route->getStartAt());
        self::assertNotNull($route->getEndAt());
    }

    #[Test]
    public function startFromActiveDoesNothing(): void
    {
        $route = new Route('Already Active Route');
        $route->start();

        $originalStartAt = $route->getStartAt();

        // Attempting to start again should be a no-op
        $route->start();

        self::assertSame(RouteStatus::ACTIVE, $route->getStatus());
        self::assertSame($originalStartAt, $route->getStartAt());
    }

    #[Test]
    public function finishFromPlannedDoesNothing(): void
    {
        $route = new Route('Planned Route');

        // Trying to finish a PLANNED route (not started) should be a no-op
        $route->finish();

        self::assertSame(RouteStatus::PLANNED, $route->getStatus());
        self::assertNull($route->getEndAt());
    }

    #[Test]
    public function startFromDoneDoesNothing(): void
    {
        $route = new Route('Completed Route');
        $route->start();
        $route->finish();

        self::assertSame(RouteStatus::DONE, $route->getStatus());

        // Trying to start a DONE route should be a no-op
        $route->start();

        self::assertSame(RouteStatus::DONE, $route->getStatus());
    }

    #[Test]
    public function finishFromDoneDoesNothing(): void
    {
        $route = new Route('Already Done Route');
        $route->start();
        $route->finish();

        $originalEndAt = $route->getEndAt();

        // Trying to finish again should be a no-op
        $route->finish();

        self::assertSame(RouteStatus::DONE, $route->getStatus());
        self::assertSame($originalEndAt, $route->getEndAt());
    }

    #[Test]
    public function startFromCancelledDoesNothing(): void
    {
        $route = new Route('Cancelled Route');
        $route->setStatus(RouteStatus::CANCELLED);

        $route->start();

        self::assertSame(RouteStatus::CANCELLED, $route->getStatus());
        self::assertNull($route->getStartAt());
    }

    #[Test]
    public function finishFromCancelledDoesNothing(): void
    {
        $route = new Route('Cancelled Route');
        $route->setStatus(RouteStatus::CANCELLED);

        $route->finish();

        self::assertSame(RouteStatus::CANCELLED, $route->getStatus());
        self::assertNull($route->getEndAt());
    }

    #[Test]
    public function fullLifecyclePlannedToActiveToDeone(): void
    {
        $route = new Route('Full Lifecycle Route');

        // Phase 1: PLANNED
        self::assertSame(RouteStatus::PLANNED, $route->getStatus());
        self::assertNull($route->getStartAt());
        self::assertNull($route->getEndAt());

        // Phase 2: ACTIVE
        $route->start();
        self::assertSame(RouteStatus::ACTIVE, $route->getStatus());
        self::assertNotNull($route->getStartAt());
        self::assertNull($route->getEndAt());

        // Phase 3: DONE
        $route->finish();
        self::assertSame(RouteStatus::DONE, $route->getStatus());
        self::assertNotNull($route->getStartAt());
        self::assertNotNull($route->getEndAt());

        // End times should be after start times
        self::assertGreaterThanOrEqual(
            $route->getStartAt()->getTimestamp(),
            $route->getEndAt()->getTimestamp(),
        );
    }

    #[Test]
    public function setStatusDirectlyAllowsAnyTransition(): void
    {
        $route = new Route('Direct Status Route');

        $route->setStatus(RouteStatus::ACTIVE);
        self::assertSame(RouteStatus::ACTIVE, $route->getStatus());

        $route->setStatus(RouteStatus::CANCELLED);
        self::assertSame(RouteStatus::CANCELLED, $route->getStatus());

        $route->setStatus(RouteStatus::PLANNED);
        self::assertSame(RouteStatus::PLANNED, $route->getStatus());

        $route->setStatus(RouteStatus::DONE);
        self::assertSame(RouteStatus::DONE, $route->getStatus());
    }
}
