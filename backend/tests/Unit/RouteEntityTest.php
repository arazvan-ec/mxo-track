<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Route;
use App\Enum\RouteStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Route::class)]
final class RouteEntityTest extends TestCase
{
    #[Test]
    public function constructorSetsName(): void
    {
        $route = new Route('Delivery Route A');

        self::assertSame('Delivery Route A', $route->getName());
        self::assertSame(RouteStatus::PLANNED, $route->getStatus());
    }

    #[Test]
    public function startTransitionsPlannedToActive(): void
    {
        $route = new Route('Test Route');
        self::assertNull($route->getStartAt());

        $route->start();

        self::assertSame(RouteStatus::ACTIVE, $route->getStatus());
        self::assertNotNull($route->getStartAt());
    }

    #[Test]
    public function startDoesNothingWhenAlreadyActive(): void
    {
        $route = new Route('Test Route');
        $route->start();
        $firstStartAt = $route->getStartAt();

        $route->start();

        self::assertSame(RouteStatus::ACTIVE, $route->getStatus());
        self::assertSame($firstStartAt, $route->getStartAt());
    }

    #[Test]
    public function finishTransitionsActiveTosDone(): void
    {
        $route = new Route('Test Route');
        $route->start();
        self::assertNull($route->getEndAt());

        $route->finish();

        self::assertSame(RouteStatus::DONE, $route->getStatus());
        self::assertNotNull($route->getEndAt());
    }

    #[Test]
    public function finishDoesNothingWhenPlanned(): void
    {
        $route = new Route('Test Route');

        $route->finish();

        self::assertSame(RouteStatus::PLANNED, $route->getStatus());
        self::assertNull($route->getEndAt());
    }

    #[Test]
    public function capacityGettersReturnNullByDefault(): void
    {
        $route = new Route('Test Route');

        self::assertNull($route->getTotalWeightKg());
        self::assertNull($route->getTotalVolumeM3());
        self::assertNull($route->getTotalParcels());
        self::assertNull($route->getTotalDistanceKm());
        self::assertNull($route->getEstimatedDurationMinutes());
    }

    #[Test]
    public function capacitySettersStoreValues(): void
    {
        $route = new Route('Test Route');

        $route->setTotalWeightKg(125.50);
        $route->setTotalVolumeM3(2.5);
        $route->setTotalParcels(10);
        $route->setTotalDistanceKm(45.75);
        $route->setEstimatedDurationMinutes(90);

        self::assertSame(125.5, $route->getTotalWeightKg());
        self::assertSame(2.5, $route->getTotalVolumeM3());
        self::assertSame(10, $route->getTotalParcels());
        self::assertSame(45.75, $route->getTotalDistanceKm());
        self::assertSame(90, $route->getEstimatedDurationMinutes());
    }

    #[Test]
    public function optionalRelationsAreNullByDefault(): void
    {
        $route = new Route('Test Route');

        self::assertNull($route->getDriver());
        self::assertNull($route->getVehicle());
        self::assertNull($route->getCustomer());
        self::assertNull($route->getOriginLocation());
    }
}
