<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Customer;
use App\Entity\CustomerLocation;
use App\Entity\Shipment;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Enum\ShipmentPriority;
use App\Enum\VehicleSkill;
use App\Service\DemoScenarioBuilder;
use App\Service\DemoScenarioResult;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class DemoScenarioBuilderTest extends TestCase
{
    private DemoScenarioBuilder $builder;

    protected function setUp(): void
    {
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hashed');
        $this->builder = new DemoScenarioBuilder($hasher);
    }

    public function testCreatesExpectedEntities(): void
    {
        $result = $this->builder->buildScenario(shipmentCount: 20);

        self::assertInstanceOf(DemoScenarioResult::class, $result);
        self::assertInstanceOf(Customer::class, $result->customer);
        self::assertSame('Logística Express Madrid', $result->customer->getName());
        self::assertInstanceOf(CustomerLocation::class, $result->warehouse);
        self::assertNotNull($result->warehouse->getLatitude());
        self::assertCount(3, $result->vehicles);
        self::assertCount(2, $result->drivers);
        self::assertCount(20, $result->shipments);
    }

    public function testVehiclesHaveCorrectSkills(): void
    {
        $result = $this->builder->buildScenario();

        // Furgoneta has FRAGILE
        self::assertContains(VehicleSkill::FRAGILE, $result->vehicles[0]->getSkills());
        // Camión refrigerado has REFRIGERATED + HEAVY_LOAD
        self::assertContains(VehicleSkill::REFRIGERATED, $result->vehicles[1]->getSkills());
        self::assertContains(VehicleSkill::HEAVY_LOAD, $result->vehicles[1]->getSkills());
        // Moto has PEDESTRIAN_ACCESS
        self::assertContains(VehicleSkill::PEDESTRIAN_ACCESS, $result->vehicles[2]->getSkills());
    }

    public function testVehiclesHaveCapacity(): void
    {
        $result = $this->builder->buildScenario();

        self::assertSame(1000.0, $result->vehicles[0]->getMaxWeightKg());
        self::assertSame(8.0, $result->vehicles[0]->getMaxVolumeM3());
        self::assertSame(50, $result->vehicles[0]->getMaxParcels());

        self::assertSame(3000.0, $result->vehicles[1]->getMaxWeightKg());
        self::assertSame(20.0, $result->vehicles[1]->getMaxVolumeM3());
        self::assertSame(100, $result->vehicles[1]->getMaxParcels());

        self::assertSame(30.0, $result->vehicles[2]->getMaxWeightKg());
        self::assertSame(0.5, $result->vehicles[2]->getMaxVolumeM3());
        self::assertSame(5, $result->vehicles[2]->getMaxParcels());
    }

    public function testDriversHaveDriverRole(): void
    {
        $result = $this->builder->buildScenario();

        foreach ($result->drivers as $driver) {
            self::assertContains('ROLE_DRIVER', $driver->getRoles());
        }
    }

    public function testDriversBelongToCustomer(): void
    {
        $result = $this->builder->buildScenario();

        foreach ($result->drivers as $driver) {
            self::assertSame($result->customer, $driver->getCustomer());
        }
    }

    public function testShipmentsHaveValidCoordinates(): void
    {
        $result = $this->builder->buildScenario(shipmentCount: 40);

        foreach ($result->shipments as $shipment) {
            self::assertNotNull($shipment->getLatitude());
            self::assertNotNull($shipment->getLongitude());
            self::assertGreaterThan(40.0, $shipment->getLatitude());
            self::assertLessThan(41.0, $shipment->getLatitude());
            self::assertGreaterThan(-4.0, $shipment->getLongitude());
            self::assertLessThan(-3.0, $shipment->getLongitude());
        }
    }

    public function testShipmentsHaveMixedPriorities(): void
    {
        $result = $this->builder->buildScenario(shipmentCount: 40);

        $priorities = array_map(
            fn (Shipment $s) => $s->getPriority(),
            $result->shipments,
        );
        $uniquePriorities = array_unique(array_map(fn ($p) => $p->value, $priorities));
        self::assertGreaterThan(1, count($uniquePriorities), 'Shipments should have mixed priorities');
    }

    public function testShipmentsBelongToCustomer(): void
    {
        $result = $this->builder->buildScenario(shipmentCount: 10);

        foreach ($result->shipments as $shipment) {
            self::assertSame($result->customer, $shipment->getCustomer());
        }
    }

    public function testConfigurableShipmentCount(): void
    {
        $result10 = $this->builder->buildScenario(shipmentCount: 10);
        $result30 = $this->builder->buildScenario(shipmentCount: 30);

        self::assertCount(10, $result10->shipments);
        self::assertCount(30, $result30->shipments);
    }

    public function testDefaultShipmentCountIs40(): void
    {
        $result = $this->builder->buildScenario();

        self::assertCount(40, $result->shipments);
    }

    public function testCustomerUserCreated(): void
    {
        $result = $this->builder->buildScenario();

        self::assertInstanceOf(User::class, $result->customerUser);
        self::assertContains('ROLE_CUSTOMER', $result->customerUser->getRoles());
        self::assertSame($result->customer, $result->customerUser->getCustomer());
    }
}
