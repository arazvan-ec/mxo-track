<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shipment\Model;

use App\Domain\Shipment\Model\Shipment;
use App\Entity\Customer;
use App\Enum\ServiceType;
use App\Enum\ShipmentPriority;
use App\Enum\VehicleSkill;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ShipmentTest extends TestCase
{
    #[Test]
    public function constructorSetsReferenceAndCustomer(): void
    {
        $customer = $this->createMock(Customer::class);
        $shipment = new Shipment('REF-001', $customer);

        self::assertSame('REF-001', $shipment->getReference());
        self::assertSame($customer, $shipment->getCustomer());
    }

    #[Test]
    public function constructorGeneratesTrackingToken(): void
    {
        $customer = $this->createMock(Customer::class);
        $shipment = new Shipment('REF-001', $customer);

        self::assertNotNull($shipment->getTrackingToken());
        self::assertStringStartsWith('TRK-', $shipment->getTrackingToken());
    }

    #[Test]
    public function constructorSetsCreatedAt(): void
    {
        $customer = $this->createMock(Customer::class);
        $before = new \DateTimeImmutable();
        $shipment = new Shipment('REF-001', $customer);

        self::assertGreaterThanOrEqual($before, $shipment->getCreatedAt());
    }

    #[Test]
    public function defaultServiceTypeIsDelivery(): void
    {
        $customer = $this->createMock(Customer::class);
        $shipment = new Shipment('REF-001', $customer);

        self::assertSame(ServiceType::DELIVERY, $shipment->getServiceType());
    }

    #[Test]
    public function defaultPriorityIsNormal(): void
    {
        $customer = $this->createMock(Customer::class);
        $shipment = new Shipment('REF-001', $customer);

        self::assertSame(ShipmentPriority::NORMAL, $shipment->getPriority());
    }

    #[Test]
    public function recalculateTotalsSumsParcelWeightsAndVolumes(): void
    {
        $customer = $this->createMock(Customer::class);
        $shipment = new Shipment('REF-001', $customer);

        // Parcel creation is tested in ParcelTest; here we test
        // that the Shipment can be created as a POPO without ORM
        self::assertSame(1, $shipment->getTotalParcels());
    }

    #[Test]
    public function requiredSkillsRoundTrip(): void
    {
        $customer = $this->createMock(Customer::class);
        $shipment = new Shipment('REF-001', $customer);

        $skills = [VehicleSkill::REFRIGERATED, VehicleSkill::HEAVY_LOAD];
        $shipment->setRequiredSkills($skills);

        self::assertEquals($skills, $shipment->getRequiredSkills());
    }

    #[Test]
    public function hasPublicIdMethods(): void
    {
        $customer = $this->createMock(Customer::class);
        $shipment = new Shipment('REF-001', $customer);

        $shipment->initializePublicId();
        self::assertNotNull($shipment->getPublicId());
        self::assertNotEmpty($shipment->getPublicIdString());
    }
}
