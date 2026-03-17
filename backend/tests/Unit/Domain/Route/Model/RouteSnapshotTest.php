<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Route\Model;

use App\Domain\Route\Model\RouteSnapshot;
use App\Domain\Route\ValueObject\RouteId;
use PHPUnit\Framework\TestCase;

final class RouteSnapshotTest extends TestCase
{
    public function testNewSnapshotHasTimestamps(): void
    {
        $snapshot = new RouteSnapshot(new RouteId('01J0000000000000000000TEST'));

        self::assertInstanceOf(\DateTimeImmutable::class, $snapshot->createdAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $snapshot->updatedAt());
        self::assertNull($snapshot->polyline());
    }

    public function testReconstituteRestoresAllProperties(): void
    {
        $routeId = new RouteId('01J0000000000000000000TEST');
        $created = new \DateTimeImmutable('2026-03-16 08:00:00');
        $updated = new \DateTimeImmutable('2026-03-16 09:00:00');

        $snapshot = RouteSnapshot::reconstitute(
            routeId: $routeId,
            polyline: 'encoded_poly',
            originalPolyline: 'orig_poly',
            actualPolyline: 'actual_poly',
            distanceBeforeKm: 50.0,
            distanceAfterKm: 35.0,
            savingsPercent: 30.0,
            drivingTimeMinutes: 60,
            deliveryTimeMinutes: 30,
            totalTimeMinutes: 90,
            originalStopOrder: [['id' => 1], ['id' => 2]],
            stopStates: [['status' => 'PENDING']],
            etas: [['stop' => 1, 'eta' => '10:00']],
            capacityValidation: ['valid' => true],
            createdAt: $created,
            updatedAt: $updated,
        );

        self::assertSame($routeId, $snapshot->routeId());
        self::assertSame('encoded_poly', $snapshot->polyline());
        self::assertSame('orig_poly', $snapshot->originalPolyline());
        self::assertSame('actual_poly', $snapshot->actualPolyline());
        self::assertSame(50.0, $snapshot->distanceBeforeKm());
        self::assertSame(35.0, $snapshot->distanceAfterKm());
        self::assertSame(30.0, $snapshot->savingsPercent());
        self::assertSame(60, $snapshot->drivingTimeMinutes());
        self::assertSame(30, $snapshot->deliveryTimeMinutes());
        self::assertSame(90, $snapshot->totalTimeMinutes());
        self::assertSame([['id' => 1], ['id' => 2]], $snapshot->originalStopOrder());
        self::assertSame([['status' => 'PENDING']], $snapshot->stopStates());
        self::assertSame([['stop' => 1, 'eta' => '10:00']], $snapshot->etas());
        self::assertSame(['valid' => true], $snapshot->capacityValidation());
        self::assertSame($created, $snapshot->createdAt());
        self::assertSame($updated, $snapshot->updatedAt());
    }

    public function testTouchUpdatesTimestamp(): void
    {
        $snapshot = new RouteSnapshot(new RouteId('01J0000000000000000000TEST'));
        $before = $snapshot->updatedAt();

        usleep(1000); // ensure time difference
        $snapshot->touch();

        self::assertGreaterThanOrEqual($before, $snapshot->updatedAt());
    }
}
