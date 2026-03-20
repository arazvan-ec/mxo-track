<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteSnapshot;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RouteSnapshot::class)]
final class RouteSnapshotTest extends TestCase
{
    #[Test]
    public function snapshotLinksToRoute(): void
    {
        $route = new Route('Test Route');
        $snapshot = new RouteSnapshot($route);

        self::assertSame($route, $snapshot->getRoute());
        self::assertNotNull($snapshot->getCreatedAt());
        self::assertNotNull($snapshot->getUpdatedAt());
    }

    #[Test]
    public function polylineFieldsWorkCorrectly(): void
    {
        $route = new Route('Test Route');
        $snapshot = new RouteSnapshot($route);

        self::assertNull($snapshot->getPolyline());

        $snapshot->setPolyline('encoded_polyline_here');
        self::assertSame('encoded_polyline_here', $snapshot->getPolyline());

        $snapshot->setOriginalPolyline('original_here');
        self::assertSame('original_here', $snapshot->getOriginalPolyline());

        $snapshot->setActualPolyline('actual_here');
        self::assertSame('actual_here', $snapshot->getActualPolyline());
    }

    #[Test]
    public function optimizationMetricsWorkCorrectly(): void
    {
        $route = new Route('Test Route');
        $snapshot = new RouteSnapshot($route);

        $snapshot->setDistanceBeforeKm(50.0);
        $snapshot->setDistanceAfterKm(35.0);
        $snapshot->setSavingsPercent(30.0);

        self::assertSame(50.0, $snapshot->getDistanceBeforeKm());
        self::assertSame(35.0, $snapshot->getDistanceAfterKm());
        self::assertSame(30.0, $snapshot->getSavingsPercent());
    }

    #[Test]
    public function timingFieldsWorkCorrectly(): void
    {
        $route = new Route('Test Route');
        $snapshot = new RouteSnapshot($route);

        $snapshot->setDrivingTimeMinutes(60);
        $snapshot->setDeliveryTimeMinutes(30);
        $snapshot->setTotalTimeMinutes(90);

        self::assertSame(60, $snapshot->getDrivingTimeMinutes());
        self::assertSame(30, $snapshot->getDeliveryTimeMinutes());
        self::assertSame(90, $snapshot->getTotalTimeMinutes());
    }

    #[Test]
    public function stopStatesAreNullByDefault(): void
    {
        $route = new Route('Test Route');
        $snapshot = new RouteSnapshot($route);

        self::assertNull($snapshot->getStopStates());
        self::assertNull($snapshot->getOriginalStopOrder());
    }

    #[Test]
    public function stopStatesCanBeSetAndRetrieved(): void
    {
        $route = new Route('Test Route');
        $snapshot = new RouteSnapshot($route);

        $states = [
            ['publicId' => 'abc', 'sequence' => 1, 'status' => 'PENDING'],
            ['publicId' => 'def', 'sequence' => 2, 'status' => 'DELIVERED', 'deliveredAt' => '2026-03-13T10:00:00+00:00'],
        ];
        $snapshot->setStopStates($states);

        self::assertSame($states, $snapshot->getStopStates());
    }

    #[Test]
    public function capacityValidationCanBeSetAndRetrieved(): void
    {
        $route = new Route('Test Route');
        $snapshot = new RouteSnapshot($route);

        $validation = [
            'valid' => true,
            'totalWeightKg' => 120.5,
            'weightUtilization' => 60.25,
        ];
        $snapshot->setCapacityValidation($validation);

        self::assertSame($validation, $snapshot->getCapacityValidation());
    }

    #[Test]
    public function touchUpdatesTimestamp(): void
    {
        $route = new Route('Test Route');
        $snapshot = new RouteSnapshot($route);

        $originalUpdatedAt = $snapshot->getUpdatedAt();

        usleep(1000);
        $snapshot->touch();

        self::assertGreaterThan($originalUpdatedAt, $snapshot->getUpdatedAt());
    }

    #[Test]
    public function allNullableFieldsAreNullByDefault(): void
    {
        $route = new Route('Test Route');
        $snapshot = new RouteSnapshot($route);

        self::assertNull($snapshot->getId());
        self::assertNull($snapshot->getPolyline());
        self::assertNull($snapshot->getOriginalPolyline());
        self::assertNull($snapshot->getActualPolyline());
        self::assertNull($snapshot->getDistanceBeforeKm());
        self::assertNull($snapshot->getDistanceAfterKm());
        self::assertNull($snapshot->getSavingsPercent());
        self::assertNull($snapshot->getDrivingTimeMinutes());
        self::assertNull($snapshot->getDeliveryTimeMinutes());
        self::assertNull($snapshot->getTotalTimeMinutes());
        self::assertNull($snapshot->getCapacityValidation());
    }
}
