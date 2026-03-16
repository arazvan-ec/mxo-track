<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Route\Model;

use App\Domain\Route\Model\Route;
use App\Domain\Route\ValueObject\Capacity;
use App\Domain\Route\ValueObject\Distance;
use App\Domain\Route\ValueObject\RouteId;
use App\Enum\RouteStatus;
use PHPUnit\Framework\TestCase;

final class RouteTest extends TestCase
{
    public function testNewRouteHasPlannedStatus(): void
    {
        $route = new Route(new RouteId('01J0000000000000000000TEST'), 'Morning route');

        self::assertSame(RouteStatus::PLANNED, $route->status());
        self::assertSame('Morning route', $route->name());
        self::assertSame('01J0000000000000000000TEST', (string) $route->id());
    }

    public function testStartTransitionsFromPlannedToActive(): void
    {
        $route = new Route(new RouteId('01J0000000000000000000TEST'), 'Test');

        $route->start();

        self::assertSame(RouteStatus::ACTIVE, $route->status());
        self::assertInstanceOf(\DateTimeImmutable::class, $route->startAt());
    }

    public function testStartThrowsWhenNotPlanned(): void
    {
        $route = new Route(new RouteId('01J0000000000000000000TEST'), 'Test');
        $route->start();

        $this->expectException(\DomainException::class);
        $route->start();
    }

    public function testFinishTransitionsFromActiveToDone(): void
    {
        $route = new Route(new RouteId('01J0000000000000000000TEST'), 'Test');
        $route->start();

        $route->finish();

        self::assertSame(RouteStatus::DONE, $route->status());
        self::assertInstanceOf(\DateTimeImmutable::class, $route->endAt());
    }

    public function testFinishThrowsWhenNotActive(): void
    {
        $route = new Route(new RouteId('01J0000000000000000000TEST'), 'Test');

        $this->expectException(\DomainException::class);
        $route->finish();
    }

    public function testReconstituteRestoresAllProperties(): void
    {
        $id = new RouteId('01J0000000000000000000TEST');
        $capacity = new Capacity(100.0, 5.0, 20);
        $distance = new Distance(42.5);
        $start = new \DateTimeImmutable('2026-03-16 08:00:00');
        $end = new \DateTimeImmutable('2026-03-16 17:00:00');
        $deleted = new \DateTimeImmutable('2026-03-16 18:00:00');

        $route = Route::reconstitute(
            id: $id,
            name: 'Reconstituted',
            status: RouteStatus::DONE,
            driverId: 42,
            vehicleId: 7,
            customerId: 3,
            originLocationId: 15,
            capacity: $capacity,
            totalDistance: $distance,
            estimatedDurationMinutes: 120,
            aiAnalysis: ['score' => 95],
            autoReoptimize: true,
            startAt: $start,
            endAt: $end,
            deletedAt: $deleted,
        );

        self::assertSame('Reconstituted', $route->name());
        self::assertSame(RouteStatus::DONE, $route->status());
        self::assertSame(42, $route->driverId());
        self::assertSame(7, $route->vehicleId());
        self::assertSame(3, $route->customerId());
        self::assertSame(15, $route->originLocationId());
        self::assertSame($capacity, $route->capacity());
        self::assertSame($distance, $route->totalDistance());
        self::assertSame(120, $route->estimatedDurationMinutes());
        self::assertSame(['score' => 95], $route->aiAnalysis());
        self::assertTrue($route->autoReoptimize());
        self::assertSame($start, $route->startAt());
        self::assertSame($end, $route->endAt());
        self::assertSame($deleted, $route->deletedAt());
    }

    public function testSoftDeleteSetsTimestamp(): void
    {
        $route = new Route(new RouteId('01J0000000000000000000TEST'), 'Test');

        self::assertNull($route->deletedAt());

        $route->softDelete();

        self::assertInstanceOf(\DateTimeImmutable::class, $route->deletedAt());
    }

    public function testSetters(): void
    {
        $route = new Route(new RouteId('01J0000000000000000000TEST'), 'Original');

        $route->setName('Updated');
        $route->assignDriver(10);
        $route->assignVehicle(5);
        $route->setCustomerId(2);
        $route->setOriginLocationId(8);
        $route->setAutoReoptimize(true);
        $route->setEstimatedDurationMinutes(90);
        $route->setAiAnalysis(['key' => 'val']);

        self::assertSame('Updated', $route->name());
        self::assertSame(10, $route->driverId());
        self::assertSame(5, $route->vehicleId());
        self::assertSame(2, $route->customerId());
        self::assertSame(8, $route->originLocationId());
        self::assertTrue($route->autoReoptimize());
        self::assertSame(90, $route->estimatedDurationMinutes());
        self::assertSame(['key' => 'val'], $route->aiAnalysis());
    }
}
