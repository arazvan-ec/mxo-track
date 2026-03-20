<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteStop;
use App\Enum\ExceptionCode;
use App\Enum\RouteStopStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RouteStop::class)]
final class RouteStopTest extends TestCase
{
    #[Test]
    public function newStopHasPendingStatus(): void
    {
        $route = new Route('Test Route');
        $stop = new RouteStop($route, 1, 'Calle Mayor 1');

        self::assertSame(RouteStopStatus::PENDING, $stop->getStatus());
        self::assertNull($stop->getDeliveredAt());
        self::assertNull($stop->getExceptionCode());
        self::assertNull($stop->getExceptionNotes());
    }

    #[Test]
    public function markDeliveredTransitionsToDeliveredStatus(): void
    {
        $route = new Route('Test Route');
        $stop = new RouteStop($route, 1, 'Calle Mayor 1');

        $stop->markDelivered();

        self::assertSame(RouteStopStatus::DELIVERED, $stop->getStatus());
        self::assertNotNull($stop->getDeliveredAt());
        self::assertNull($stop->getExceptionCode());
        self::assertNull($stop->getExceptionNotes());
    }

    #[Test]
    public function markDeliveredIsIdempotent(): void
    {
        $route = new Route('Test Route');
        $stop = new RouteStop($route, 1, 'Calle Mayor 1');

        $stop->markDelivered();
        $firstDeliveredAt = $stop->getDeliveredAt();

        // Calling again should be a no-op (already DELIVERED)
        $stop->markDelivered();

        self::assertSame(RouteStopStatus::DELIVERED, $stop->getStatus());
        self::assertSame($firstDeliveredAt, $stop->getDeliveredAt());
    }

    #[Test]
    public function markExceptionTransitionsToExceptionStatus(): void
    {
        $route = new Route('Test Route');
        $stop = new RouteStop($route, 1, 'Gran Via 50');

        $stop->markException(ExceptionCode::ABSENT, 'Nobody answered the door');

        self::assertSame(RouteStopStatus::EXCEPTION, $stop->getStatus());
        self::assertSame(ExceptionCode::ABSENT, $stop->getExceptionCode());
        self::assertSame('Nobody answered the door', $stop->getExceptionNotes());
    }

    #[Test]
    public function markExceptionOverwritesPreviousException(): void
    {
        $route = new Route('Test Route');
        $stop = new RouteStop($route, 1, 'Address');

        $stop->markException(ExceptionCode::ABSENT, 'First attempt');
        $stop->markException(ExceptionCode::WRONG_ADDRESS, 'Actually wrong address');

        self::assertSame(RouteStopStatus::EXCEPTION, $stop->getStatus());
        self::assertSame(ExceptionCode::WRONG_ADDRESS, $stop->getExceptionCode());
        self::assertSame('Actually wrong address', $stop->getExceptionNotes());
    }

    #[Test]
    public function markDeliveredClearsExceptionData(): void
    {
        $route = new Route('Test Route');
        $stop = new RouteStop($route, 1, 'Address');

        // First mark as exception
        $stop->markException(ExceptionCode::REFUSED, 'Refused delivery');

        self::assertSame(RouteStopStatus::EXCEPTION, $stop->getStatus());

        // Then override with delivery
        $stop->markDelivered();

        self::assertSame(RouteStopStatus::DELIVERED, $stop->getStatus());
        self::assertNotNull($stop->getDeliveredAt());
        self::assertNull($stop->getExceptionCode());
        self::assertNull($stop->getExceptionNotes());
    }

    #[Test]
    public function stopReferencesItsRoute(): void
    {
        $route = new Route('Parent Route');
        $stop = new RouteStop($route, 3, 'Calle Alcala 10');

        self::assertSame($route, $stop->getRoute());
        self::assertSame(3, $stop->getSequence());
        self::assertSame('Calle Alcala 10', $stop->getAddress());
    }

    #[Test]
    public function stopOptionalFieldsAreNullByDefault(): void
    {
        $route = new Route('Test Route');
        $stop = new RouteStop($route, 1, 'Address');

        self::assertNull($stop->getLatitude());
        self::assertNull($stop->getLongitude());
        self::assertNull($stop->getRecipientName());
        self::assertNull($stop->getRecipientPhone());
        self::assertNull($stop->getNotes());
        self::assertNull($stop->getShipment());
        self::assertFalse($stop->isOrigin());
    }

    #[Test]
    public function markSkippedTransitionsToSkippedStatus(): void
    {
        $route = new Route('Test Route');
        $stop = new RouteStop($route, 1, 'Address');

        $stop->markSkipped('Too far from route');

        self::assertSame(RouteStopStatus::SKIPPED, $stop->getStatus());
        self::assertSame('Too far from route', $stop->getExceptionNotes());
    }

    #[Test]
    public function markSkippedWithoutReason(): void
    {
        $route = new Route('Test Route');
        $stop = new RouteStop($route, 1, 'Address');

        $stop->markSkipped();

        self::assertSame(RouteStopStatus::SKIPPED, $stop->getStatus());
        self::assertNull($stop->getExceptionNotes());
    }

    #[Test]
    public function allExceptionCodesAreAccepted(): void
    {
        $route = new Route('Test Route');

        foreach (ExceptionCode::cases() as $code) {
            $stop = new RouteStop($route, 1, 'Address');
            $stop->markException($code, 'Test comment for ' . $code->value);

            self::assertSame($code, $stop->getExceptionCode());
            self::assertSame(RouteStopStatus::EXCEPTION, $stop->getStatus());
        }
    }
}
