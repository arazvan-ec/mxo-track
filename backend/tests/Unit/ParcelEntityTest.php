<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Customer;
use App\Entity\Parcel;
use App\Entity\Shipment;
use App\Enum\ParcelStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Parcel::class)]
final class ParcelEntityTest extends TestCase
{
    private Shipment $shipment;

    protected function setUp(): void
    {
        $this->shipment = new Shipment('REF-001', new Customer('Test'));
    }

    #[Test]
    public function constructorSetsPropertiesAndAddsToShipment(): void
    {
        $parcel = new Parcel($this->shipment, 1, 2.5, 0.015);

        self::assertSame($this->shipment, $parcel->getShipment());
        self::assertSame(1, $parcel->getSequenceNumber());
        self::assertSame(2.5, $parcel->getWeightKg());
        self::assertSame(0.015, $parcel->getVolumeM3());
        self::assertTrue($this->shipment->getParcels()->contains($parcel));
    }

    #[Test]
    public function defaultStatusIsRegistered(): void
    {
        $parcel = new Parcel($this->shipment, 1, 1.0, 0.01);

        self::assertSame(ParcelStatus::REGISTERED, $parcel->getStatus());
    }

    #[Test]
    public function transitionChangesStatus(): void
    {
        $parcel = new Parcel($this->shipment, 1, 1.0, 0.01);

        $parcel->transition(ParcelStatus::IN_TRANSIT);

        self::assertSame(ParcelStatus::IN_TRANSIT, $parcel->getStatus());
    }

    #[Test]
    public function getLabelFormatsCorrectly(): void
    {
        $parcel = new Parcel($this->shipment, 2, 1.0, 0.01);

        // Shipment has totalParcels=1 by default but parcel is seq 2
        self::assertSame('2/1', $parcel->getLabel());
    }

    #[Test]
    public function eanIsNullByDefault(): void
    {
        $parcel = new Parcel($this->shipment, 1, 1.0, 0.01);

        self::assertNull($parcel->getEan());

        $parcel->setEan('1234567890123');
        self::assertSame('1234567890123', $parcel->getEan());
    }

    #[Test]
    public function weightAndVolumeCanBeUpdated(): void
    {
        $parcel = new Parcel($this->shipment, 1, 1.0, 0.01);

        $parcel->setWeightKg(5.5);
        $parcel->setVolumeM3(0.05);

        self::assertSame(5.5, $parcel->getWeightKg());
        self::assertSame(0.05, $parcel->getVolumeM3());
    }
}
