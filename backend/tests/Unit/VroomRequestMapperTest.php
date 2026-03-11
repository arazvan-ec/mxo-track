<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\CustomerLocation;
use App\Entity\Shipment;
use App\Entity\Vehicle;
use App\Enum\ShipmentPriority;
use App\Enum\VehicleSkill;
use App\Service\VroomRequestMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(VroomRequestMapper::class)]
final class VroomRequestMapperTest extends TestCase
{
    private VroomRequestMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new VroomRequestMapper();
    }

    #[Test]
    public function mapVehiclesConvertsCapacityToVroomFormat(): void
    {
        $vehicle = $this->createMock(Vehicle::class);
        $vehicle->method('getMaxWeightKg')->willReturn(1000.0);
        $vehicle->method('getMaxVolumeM3')->willReturn(5.0);
        $vehicle->method('getMaxParcels')->willReturn(50);
        $vehicle->method('getSkills')->willReturn([]);

        $result = $this->mapper->mapVehicles([$vehicle], null);

        self::assertCount(1, $result['vroomVehicles']);
        $vroomVehicle = $result['vroomVehicles'][0];

        self::assertSame(0, $vroomVehicle['id']);
        self::assertSame([1_000_000, 5_000_000, 50], $vroomVehicle['capacity']);
        self::assertArrayNotHasKey('start', $vroomVehicle);
        self::assertArrayNotHasKey('end', $vroomVehicle);
    }

    #[Test]
    public function mapVehiclesIncludesOriginCoordinates(): void
    {
        $vehicle = $this->createMock(Vehicle::class);
        $vehicle->method('getMaxWeightKg')->willReturn(500.0);
        $vehicle->method('getMaxVolumeM3')->willReturn(2.0);
        $vehicle->method('getMaxParcels')->willReturn(20);
        $vehicle->method('getSkills')->willReturn([]);

        $origin = $this->createMock(CustomerLocation::class);
        $origin->method('getLatitude')->willReturn(40.4168);
        $origin->method('getLongitude')->willReturn(-3.7038);

        $result = $this->mapper->mapVehicles([$vehicle], $origin);
        $vroomVehicle = $result['vroomVehicles'][0];

        self::assertSame([-3.7038, 40.4168], $vroomVehicle['start']);
        self::assertSame([-3.7038, 40.4168], $vroomVehicle['end']);
    }

    #[Test]
    public function mapVehiclesIncludesSkills(): void
    {
        $vehicle = $this->createMock(Vehicle::class);
        $vehicle->method('getMaxWeightKg')->willReturn(100.0);
        $vehicle->method('getMaxVolumeM3')->willReturn(1.0);
        $vehicle->method('getMaxParcels')->willReturn(10);
        $vehicle->method('getSkills')->willReturn([VehicleSkill::REFRIGERATED, VehicleSkill::HEAVY_LOAD]);

        $result = $this->mapper->mapVehicles([$vehicle], null);
        $vroomVehicle = $result['vroomVehicles'][0];

        self::assertSame([1, 2], $vroomVehicle['skills']);
    }

    #[Test]
    public function mapVehiclesRespectsMaxTasks(): void
    {
        $vehicle = $this->createMock(Vehicle::class);
        $vehicle->method('getMaxWeightKg')->willReturn(100.0);
        $vehicle->method('getMaxVolumeM3')->willReturn(1.0);
        $vehicle->method('getMaxParcels')->willReturn(10);
        $vehicle->method('getSkills')->willReturn([]);

        $result = $this->mapper->mapVehicles([$vehicle], null, 5);
        $vroomVehicle = $result['vroomVehicles'][0];

        self::assertSame(5, $vroomVehicle['max_tasks']);
    }

    #[Test]
    public function mapVehiclesHandlesNullCapacity(): void
    {
        $vehicle = $this->createMock(Vehicle::class);
        $vehicle->method('getMaxWeightKg')->willReturn(null);
        $vehicle->method('getMaxVolumeM3')->willReturn(null);
        $vehicle->method('getMaxParcels')->willReturn(null);
        $vehicle->method('getSkills')->willReturn([]);

        $result = $this->mapper->mapVehicles([$vehicle], null);
        $vroomVehicle = $result['vroomVehicles'][0];

        // Null values should map to large defaults
        self::assertSame([999999, 999999, 9999], $vroomVehicle['capacity']);
    }

    #[Test]
    public function mapJobsConvertsShipmentToVroomFormat(): void
    {
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getLatitude')->willReturn(40.4168);
        $shipment->method('getLongitude')->willReturn(-3.7038);
        $shipment->method('getTotalWeightKg')->willReturn(10.5);
        $shipment->method('getTotalVolumeM3')->willReturn(0.05);
        $shipment->method('getTotalParcels')->willReturn(3);
        $shipment->method('getServiceTimeSeconds')->willReturn(600);
        $shipment->method('getPriority')->willReturn(ShipmentPriority::HIGH);
        $shipment->method('getPreferredWindowStart')->willReturn(null);
        $shipment->method('getPreferredWindowEnd')->willReturn(null);
        $shipment->method('getRequiredSkills')->willReturn([]);

        $result = $this->mapper->mapJobs([$shipment]);

        self::assertCount(1, $result['vroomJobs']);
        $job = $result['vroomJobs'][0];

        self::assertSame(0, $job['id']);
        self::assertSame([-3.7038, 40.4168], $job['location']);
        self::assertSame(600, $job['service']);
        self::assertSame([10500, 50000, 3], $job['amount']);
        self::assertSame(50, $job['priority']);
        self::assertArrayNotHasKey('time_windows', $job);
    }

    #[Test]
    public function mapJobsSkipsShipmentsWithoutCoordinates(): void
    {
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getLatitude')->willReturn(null);
        $shipment->method('getLongitude')->willReturn(null);

        $result = $this->mapper->mapJobs([$shipment]);

        self::assertCount(0, $result['vroomJobs']);
        self::assertCount(0, $result['shipmentMap']);
    }

    #[Test]
    public function mapJobsUsesDefaultServiceTime(): void
    {
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getLatitude')->willReturn(40.0);
        $shipment->method('getLongitude')->willReturn(-3.0);
        $shipment->method('getTotalWeightKg')->willReturn(1.0);
        $shipment->method('getTotalVolumeM3')->willReturn(0.01);
        $shipment->method('getTotalParcels')->willReturn(1);
        $shipment->method('getServiceTimeSeconds')->willReturn(null);
        $shipment->method('getPriority')->willReturn(ShipmentPriority::NORMAL);
        $shipment->method('getPreferredWindowStart')->willReturn(null);
        $shipment->method('getPreferredWindowEnd')->willReturn(null);
        $shipment->method('getRequiredSkills')->willReturn([]);

        $result = $this->mapper->mapJobs([$shipment]);

        self::assertSame(300, $result['vroomJobs'][0]['service']);
    }

    #[Test]
    public function mapJobsIncludesTimeWindows(): void
    {
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getLatitude')->willReturn(40.0);
        $shipment->method('getLongitude')->willReturn(-3.0);
        $shipment->method('getTotalWeightKg')->willReturn(1.0);
        $shipment->method('getTotalVolumeM3')->willReturn(0.01);
        $shipment->method('getTotalParcels')->willReturn(1);
        $shipment->method('getServiceTimeSeconds')->willReturn(300);
        $shipment->method('getPriority')->willReturn(ShipmentPriority::NORMAL);
        $shipment->method('getPreferredWindowStart')->willReturn(new \DateTimeImmutable('09:00'));
        $shipment->method('getPreferredWindowEnd')->willReturn(new \DateTimeImmutable('14:00'));
        $shipment->method('getRequiredSkills')->willReturn([]);

        $result = $this->mapper->mapJobs([$shipment]);
        $job = $result['vroomJobs'][0];

        self::assertArrayHasKey('time_windows', $job);
        self::assertSame([[32400, 50400]], $job['time_windows']);
    }

    #[Test]
    public function mapJobsIncludesRequiredSkills(): void
    {
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getLatitude')->willReturn(40.0);
        $shipment->method('getLongitude')->willReturn(-3.0);
        $shipment->method('getTotalWeightKg')->willReturn(1.0);
        $shipment->method('getTotalVolumeM3')->willReturn(0.01);
        $shipment->method('getTotalParcels')->willReturn(1);
        $shipment->method('getServiceTimeSeconds')->willReturn(300);
        $shipment->method('getPriority')->willReturn(ShipmentPriority::NORMAL);
        $shipment->method('getPreferredWindowStart')->willReturn(null);
        $shipment->method('getPreferredWindowEnd')->willReturn(null);
        $shipment->method('getRequiredSkills')->willReturn([VehicleSkill::REFRIGERATED]);

        $result = $this->mapper->mapJobs([$shipment]);

        self::assertSame([1], $result['vroomJobs'][0]['skills']);
    }
}
