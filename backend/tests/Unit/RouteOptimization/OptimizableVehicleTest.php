<?php

declare(strict_types=1);

namespace App\Tests\Unit\RouteOptimization;

use App\RouteOptimization\OptimizableVehicle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OptimizableVehicle::class)]
final class OptimizableVehicleTest extends TestCase
{
    #[Test]
    public function createWithShiftTimesStoresFields(): void
    {
        $vehicle = new OptimizableVehicle(
            id: 'v1',
            shiftStartSeconds: 28800,  // 08:00
            shiftEndSeconds: 64800,    // 18:00
        );

        self::assertSame(28800, $vehicle->shiftStartSeconds);
        self::assertSame(64800, $vehicle->shiftEndSeconds);
    }

    #[Test]
    public function shiftTimesDefaultToNull(): void
    {
        $vehicle = new OptimizableVehicle(id: 'v2');

        self::assertNull($vehicle->shiftStartSeconds);
        self::assertNull($vehicle->shiftEndSeconds);
    }
}
