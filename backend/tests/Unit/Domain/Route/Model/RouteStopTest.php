<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Route\Model;

use App\Domain\Route\Model\RouteStop;
use App\Domain\Route\ValueObject\Coordinate;
use App\Domain\Route\ValueObject\RouteId;
use App\Domain\Route\ValueObject\StopId;
use App\Domain\Route\ValueObject\TimeWindow;
use App\Enum\ExceptionCode;
use App\Enum\RouteStopStatus;
use PHPUnit\Framework\TestCase;

final class RouteStopTest extends TestCase
{
    private function createStop(): RouteStop
    {
        return new RouteStop(
            new StopId('01J00000000000000000000STOP'),
            new RouteId('01J0000000000000000000TEST'),
            1,
            '123 Main St',
        );
    }

    public function testNewStopIsPending(): void
    {
        $stop = $this->createStop();

        self::assertSame(RouteStopStatus::PENDING, $stop->status());
        self::assertSame(1, $stop->sequence());
        self::assertSame('123 Main St', $stop->address());
        self::assertNull($stop->deliveredAt());
    }

    public function testMarkDelivered(): void
    {
        $stop = $this->createStop();

        $stop->markDelivered();

        self::assertSame(RouteStopStatus::DELIVERED, $stop->status());
        self::assertInstanceOf(\DateTimeImmutable::class, $stop->deliveredAt());
    }

    public function testMarkDeliveredClearsException(): void
    {
        $stop = $this->createStop();
        $stop->markException(ExceptionCode::ABSENT, 'Nobody home');

        $stop->markDelivered();

        self::assertSame(RouteStopStatus::DELIVERED, $stop->status());
        self::assertNull($stop->exceptionCode());
        self::assertNull($stop->exceptionNotes());
    }

    public function testMarkDeliveredIsIdempotent(): void
    {
        $stop = $this->createStop();
        $stop->markDelivered();
        $firstDeliveredAt = $stop->deliveredAt();

        $stop->markDelivered();

        self::assertSame($firstDeliveredAt, $stop->deliveredAt());
    }

    public function testMarkException(): void
    {
        $stop = $this->createStop();

        $stop->markException(ExceptionCode::WRONG_ADDRESS, 'Address does not exist');

        self::assertSame(RouteStopStatus::EXCEPTION, $stop->status());
        self::assertSame(ExceptionCode::WRONG_ADDRESS, $stop->exceptionCode());
        self::assertSame('Address does not exist', $stop->exceptionNotes());
    }

    public function testReconstituteRestoresAllProperties(): void
    {
        $stopId = new StopId('01J00000000000000000000STOP');
        $routeId = new RouteId('01J0000000000000000000TEST');
        $coord = new Coordinate(19.4326, -99.1332);
        $deliveredAt = new \DateTimeImmutable('2026-03-16 14:30:00');
        $window = new TimeWindow(
            new \DateTimeImmutable('2026-03-16 08:00:00'),
            new \DateTimeImmutable('2026-03-16 12:00:00'),
        );

        $stop = RouteStop::reconstitute(
            id: $stopId,
            routeId: $routeId,
            sequence: 3,
            address: '456 Oak Ave',
            status: RouteStopStatus::DELIVERED,
            coordinate: $coord,
            recipientName: 'John Doe',
            recipientPhone: '+5215551234567',
            notes: 'Ring bell',
            aiNotes: 'Frequent delays here',
            isOrigin: false,
            deliveredAt: $deliveredAt,
            exceptionCode: null,
            exceptionNotes: null,
            deliveryWindow: $window,
            shipmentPublicId: 'SHP-001',
        );

        self::assertSame($stopId, $stop->id());
        self::assertSame($routeId, $stop->routeId());
        self::assertSame(3, $stop->sequence());
        self::assertSame('456 Oak Ave', $stop->address());
        self::assertSame(RouteStopStatus::DELIVERED, $stop->status());
        self::assertSame($coord, $stop->coordinate());
        self::assertSame('John Doe', $stop->recipientName());
        self::assertSame('+5215551234567', $stop->recipientPhone());
        self::assertSame('Ring bell', $stop->notes());
        self::assertSame('Frequent delays here', $stop->aiNotes());
        self::assertFalse($stop->isOrigin());
        self::assertSame($deliveredAt, $stop->deliveredAt());
        self::assertSame($window, $stop->deliveryWindow());
        self::assertSame('SHP-001', $stop->shipmentPublicId());
    }

    public function testSetters(): void
    {
        $stop = $this->createStop();
        $coord = new Coordinate(40.7128, -74.0060);

        $stop->setSequence(5);
        $stop->setAddress('789 New St');
        $stop->setCoordinate($coord);
        $stop->setRecipientName('Jane');
        $stop->setRecipientPhone('+1234567890');
        $stop->setNotes('Leave at door');
        $stop->setAiNotes('AI note');
        $stop->setOrigin(true);
        $stop->setShipmentPublicId('SHP-999');

        self::assertSame(5, $stop->sequence());
        self::assertSame('789 New St', $stop->address());
        self::assertSame($coord, $stop->coordinate());
        self::assertSame('Jane', $stop->recipientName());
        self::assertSame('+1234567890', $stop->recipientPhone());
        self::assertSame('Leave at door', $stop->notes());
        self::assertSame('AI note', $stop->aiNotes());
        self::assertTrue($stop->isOrigin());
        self::assertSame('SHP-999', $stop->shipmentPublicId());
    }
}
