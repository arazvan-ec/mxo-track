<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Event;

use App\Domain\Event\RouteReoptimized;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RouteReoptimized::class)]
final class RouteReoptimizedTest extends TestCase
{
    #[Test]
    public function trigger_field_stores_value(): void
    {
        $event = new RouteReoptimized(
            routePublicId: 'abc',
            improvementPercent: 10.5,
            distanceKm: 42.0,
            durationMinutes: 120,
            pendingStopsCount: 5,
            trigger: 'exception',
        );

        self::assertSame('exception', $event->trigger);
    }

    #[Test]
    public function trigger_defaults_to_manual(): void
    {
        $event = new RouteReoptimized(
            routePublicId: 'abc',
            improvementPercent: 10.5,
            distanceKm: 42.0,
            durationMinutes: 120,
            pendingStopsCount: 5,
        );

        self::assertSame('manual', $event->trigger);
    }
}
