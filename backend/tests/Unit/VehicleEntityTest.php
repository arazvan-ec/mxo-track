<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Vehicle;
use App\Enum\VehicleSkill;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Vehicle::class)]
final class VehicleEntityTest extends TestCase
{
    #[Test]
    public function constructorSetsName(): void
    {
        $vehicle = new Vehicle('Furgoneta 1');

        self::assertSame('Furgoneta 1', $vehicle->getName());
    }

    #[Test]
    public function isActiveByDefault(): void
    {
        $vehicle = new Vehicle('Test');

        self::assertTrue($vehicle->isActive());
    }

    #[Test]
    public function capacitiesAreNullByDefault(): void
    {
        $vehicle = new Vehicle('Test');

        self::assertNull($vehicle->getMaxWeightKg());
        self::assertNull($vehicle->getMaxVolumeM3());
        self::assertNull($vehicle->getMaxParcels());
    }

    #[Test]
    public function capacitySettersWork(): void
    {
        $vehicle = new Vehicle('Test');
        $vehicle->setMaxWeightKg(1000.0);
        $vehicle->setMaxVolumeM3(12.5);
        $vehicle->setMaxParcels(50);

        self::assertSame(1000.0, $vehicle->getMaxWeightKg());
        self::assertSame(12.5, $vehicle->getMaxVolumeM3());
        self::assertSame(50, $vehicle->getMaxParcels());
    }

    #[Test]
    public function skillsRoundTrip(): void
    {
        $vehicle = new Vehicle('Test');
        $skills = [VehicleSkill::REFRIGERATED, VehicleSkill::HEAVY_LOAD];

        $vehicle->setSkills($skills);

        self::assertSame($skills, $vehicle->getSkills());
    }

    #[Test]
    public function skillsDefaultToEmpty(): void
    {
        $vehicle = new Vehicle('Test');

        self::assertSame([], $vehicle->getSkills());
    }

    #[Test]
    public function traccarDeviceIdNullByDefault(): void
    {
        $vehicle = new Vehicle('Test');

        self::assertNull($vehicle->getTraccarDeviceId());
    }
}
