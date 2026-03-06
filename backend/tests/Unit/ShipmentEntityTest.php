<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Customer;
use App\Entity\Parcel;
use App\Entity\Shipment;
use App\Enum\ShipmentPriority;
use App\Enum\VehicleSkill;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Shipment::class)]
final class ShipmentEntityTest extends TestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        $this->customer = new Customer('Test Customer');
    }

    #[Test]
    public function constructorSetsReferenceAndCustomer(): void
    {
        $shipment = new Shipment('REF-001', $this->customer);

        self::assertSame('REF-001', $shipment->getReference());
        self::assertSame($this->customer, $shipment->getCustomer());
    }

    #[Test]
    public function trackingTokenIsGeneratedAutomatically(): void
    {
        $shipment = new Shipment('REF-001', $this->customer);

        $token = $shipment->getTrackingToken();
        self::assertNotNull($token);
        self::assertMatchesRegularExpression('/^TRK-[A-F0-9]{4}-[A-F0-9]{4}$/', $token);
    }

    #[Test]
    public function defaultPriorityIsNormal(): void
    {
        $shipment = new Shipment('REF-001', $this->customer);

        self::assertSame(ShipmentPriority::NORMAL, $shipment->getPriority());
    }

    #[Test]
    public function defaultTotalParcelsIsOne(): void
    {
        $shipment = new Shipment('REF-001', $this->customer);

        self::assertSame(1, $shipment->getTotalParcels());
    }

    #[Test]
    public function recalculateTotalsSumsParcels(): void
    {
        $shipment = new Shipment('REF-001', $this->customer);
        new Parcel($shipment, 1, 2.5, 0.01);
        new Parcel($shipment, 2, 3.0, 0.02);

        $shipment->recalculateTotals();

        self::assertSame(5.5, $shipment->getTotalWeightKg());
        self::assertSame(0.03, $shipment->getTotalVolumeM3());
        self::assertSame(2, $shipment->getTotalParcels());
    }

    #[Test]
    public function requiredSkillsRoundTrip(): void
    {
        $shipment = new Shipment('REF-001', $this->customer);
        $skills = [VehicleSkill::REFRIGERATED, VehicleSkill::HAZMAT];

        $shipment->setRequiredSkills($skills);

        self::assertSame($skills, $shipment->getRequiredSkills());
    }

    #[Test]
    public function requiredSkillsDefaultsToEmpty(): void
    {
        $shipment = new Shipment('REF-001', $this->customer);

        self::assertSame([], $shipment->getRequiredSkills());
    }

    #[Test]
    public function addAndRemoveParcel(): void
    {
        $shipment = new Shipment('REF-001', $this->customer);
        $parcel = new Parcel($shipment, 1, 1.0, 0.01);

        self::assertCount(1, $shipment->getParcels());

        $shipment->removeParcel($parcel);

        self::assertCount(0, $shipment->getParcels());
    }

    #[Test]
    public function weightAndVolumeSettersWork(): void
    {
        $shipment = new Shipment('REF-001', $this->customer);

        $shipment->setTotalWeightKg(15.5);
        $shipment->setTotalVolumeM3(0.025);

        self::assertSame(15.5, $shipment->getTotalWeightKg());
        self::assertSame(0.025, $shipment->getTotalVolumeM3());
    }
}
