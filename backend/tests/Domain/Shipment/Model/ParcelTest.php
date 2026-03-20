<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shipment\Model;

use App\Domain\Shipment\Model\Parcel;
use App\Domain\Shipment\Model\Shipment;
use App\Entity\Customer;
use App\Enum\ParcelStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ParcelTest extends TestCase
{
    #[Test]
    public function constructorSetsFields(): void
    {
        $customer = $this->createMock(Customer::class);
        $shipment = new Shipment('REF-001', $customer);
        $parcel = new Parcel($shipment, 1, 2.5, 0.01);

        self::assertSame($shipment, $parcel->getShipment());
        self::assertSame(1, $parcel->getSequenceNumber());
        self::assertSame(2.5, $parcel->getWeightKg());
        self::assertSame(0.01, $parcel->getVolumeM3());
    }

    #[Test]
    public function constructorAddsParcelToShipment(): void
    {
        $customer = $this->createMock(Customer::class);
        $shipment = new Shipment('REF-001', $customer);
        $parcel = new Parcel($shipment, 1, 2.5, 0.01);

        self::assertTrue($shipment->getParcels()->contains($parcel));
    }

    #[Test]
    public function defaultStatusIsRegistered(): void
    {
        $customer = $this->createMock(Customer::class);
        $shipment = new Shipment('REF-001', $customer);
        $parcel = new Parcel($shipment, 1, 1.0, 0.001);

        self::assertSame(ParcelStatus::REGISTERED, $parcel->getStatus());
    }

    #[Test]
    public function transitionChangesStatus(): void
    {
        $customer = $this->createMock(Customer::class);
        $shipment = new Shipment('REF-001', $customer);
        $parcel = new Parcel($shipment, 1, 1.0, 0.001);

        $parcel->transition(ParcelStatus::IN_TRANSIT);
        self::assertSame(ParcelStatus::IN_TRANSIT, $parcel->getStatus());
    }

    #[Test]
    public function getLabelFormatsCorrectly(): void
    {
        $customer = $this->createMock(Customer::class);
        $shipment = new Shipment('REF-001', $customer);
        $parcel = new Parcel($shipment, 2, 1.0, 0.001);

        self::assertSame('2/1', $parcel->getLabel());
    }

    #[Test]
    public function hasTimestamps(): void
    {
        $customer = $this->createMock(Customer::class);
        $shipment = new Shipment('REF-001', $customer);
        $parcel = new Parcel($shipment, 1, 1.0, 0.001);

        self::assertNotNull($parcel->getCreatedAt());
        self::assertNotNull($parcel->getUpdatedAt());
    }
}
