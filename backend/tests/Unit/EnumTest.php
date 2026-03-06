<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Enum\ParcelStatus;
use App\Enum\RouteStatus;
use App\Enum\ShipmentPriority;
use App\Enum\VehicleSkill;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShipmentPriority::class)]
#[CoversClass(VehicleSkill::class)]
#[CoversClass(ParcelStatus::class)]
#[CoversClass(RouteStatus::class)]
final class EnumTest extends TestCase
{
    #[Test]
    #[DataProvider('shipmentPriorityProvider')]
    public function shipmentPriorityToVroomReturnsValue(ShipmentPriority $priority): void
    {
        self::assertSame($priority->value, $priority->toVroomPriority());
    }

    /** @return iterable<string, array{ShipmentPriority}> */
    public static function shipmentPriorityProvider(): iterable
    {
        foreach (ShipmentPriority::cases() as $case) {
            yield $case->name => [$case];
        }
    }

    #[Test]
    public function allShipmentPrioritiesHaveLabels(): void
    {
        foreach (ShipmentPriority::cases() as $priority) {
            self::assertNotEmpty($priority->label());
        }
    }

    #[Test]
    public function shipmentPriorityLabelsAreSpanish(): void
    {
        self::assertSame('Baja', ShipmentPriority::LOW->label());
        self::assertSame('Normal', ShipmentPriority::NORMAL->label());
        self::assertSame('Alta', ShipmentPriority::HIGH->label());
        self::assertSame('Urgente', ShipmentPriority::URGENT->label());
    }

    #[Test]
    public function allVehicleSkillsHaveLabels(): void
    {
        foreach (VehicleSkill::cases() as $skill) {
            self::assertNotEmpty($skill->label());
        }
    }

    #[Test]
    public function vehicleSkillLabelsAreSpanish(): void
    {
        self::assertSame('Transporte refrigerado', VehicleSkill::REFRIGERATED->label());
        self::assertSame('Carga pesada', VehicleSkill::HEAVY_LOAD->label());
        self::assertSame('Materiales peligrosos', VehicleSkill::HAZMAT->label());
    }

    #[Test]
    public function allParcelStatusesHaveLabels(): void
    {
        foreach (ParcelStatus::cases() as $status) {
            self::assertNotEmpty($status->label());
        }
    }

    #[Test]
    public function parcelStatusLabelsAreSpanish(): void
    {
        self::assertSame('Registrado', ParcelStatus::REGISTERED->label());
        self::assertSame('En almacén', ParcelStatus::IN_WAREHOUSE->label());
        self::assertSame('Entregado', ParcelStatus::DELIVERED->label());
    }

    #[Test]
    public function routeStatusValues(): void
    {
        self::assertSame('PLANNED', RouteStatus::PLANNED->value);
        self::assertSame('ACTIVE', RouteStatus::ACTIVE->value);
        self::assertSame('DONE', RouteStatus::DONE->value);
        self::assertSame('CANCELLED', RouteStatus::CANCELLED->value);
    }

    #[Test]
    public function routeStatusHasFourCases(): void
    {
        self::assertCount(4, RouteStatus::cases());
    }

    #[Test]
    public function vehicleSkillIntValues(): void
    {
        self::assertSame(1, VehicleSkill::REFRIGERATED->value);
        self::assertSame(2, VehicleSkill::HEAVY_LOAD->value);
        self::assertSame(3, VehicleSkill::PEDESTRIAN_ACCESS->value);
        self::assertSame(4, VehicleSkill::HAZMAT->value);
        self::assertSame(5, VehicleSkill::FRAGILE->value);
    }

    #[Test]
    public function shipmentPriorityIntValues(): void
    {
        self::assertSame(0, ShipmentPriority::LOW->value);
        self::assertSame(25, ShipmentPriority::NORMAL->value);
        self::assertSame(50, ShipmentPriority::HIGH->value);
        self::assertSame(75, ShipmentPriority::URGENT->value);
        self::assertSame(100, ShipmentPriority::CRITICAL->value);
    }
}
