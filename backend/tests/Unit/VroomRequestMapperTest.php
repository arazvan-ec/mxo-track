<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Customer;
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
    public function mapVehiclesConvertsCapacitiesToIntegerUnits(): void
    {
        $vehicle = new Vehicle('Van 1');
        $vehicle->setMaxWeightKg(500.0);
        $vehicle->setMaxVolumeM3(3.5);
        $vehicle->setMaxParcels(20);

        $result = $this->mapper->mapVehicles([$vehicle], null);

        $vv = $result['vroomVehicles'][0];
        self::assertSame([500_000, 3_500_000, 20], $vv['capacity']);
    }

    #[Test]
    public function mapVehiclesNullCapacitiesDefaultTo999999(): void
    {
        $vehicle = new Vehicle('Van 1');
        // No capacities set → all null

        $result = $this->mapper->mapVehicles([$vehicle], null);

        $vv = $result['vroomVehicles'][0];
        self::assertSame([999999, 999999, 9999], $vv['capacity']);
    }

    #[Test]
    public function mapVehiclesIncludesOriginCoords(): void
    {
        $vehicle = new Vehicle('Van 1');
        $customer = new Customer('Test');
        $origin = new CustomerLocation($customer, 'Warehouse', 'Calle Test');
        $origin->setLatitude(40.4168);
        $origin->setLongitude(-3.7038);

        $result = $this->mapper->mapVehicles([$vehicle], $origin);

        $vv = $result['vroomVehicles'][0];
        self::assertSame([-3.7038, 40.4168], $vv['start']); // [lng, lat]
        self::assertSame([-3.7038, 40.4168], $vv['end']);
    }

    #[Test]
    public function mapVehiclesExcludesOriginWhenNull(): void
    {
        $vehicle = new Vehicle('Van 1');

        $result = $this->mapper->mapVehicles([$vehicle], null);

        $vv = $result['vroomVehicles'][0];
        self::assertArrayNotHasKey('start', $vv);
        self::assertArrayNotHasKey('end', $vv);
    }

    #[Test]
    public function mapVehiclesExcludesOriginWhenCoordsNull(): void
    {
        $vehicle = new Vehicle('Van 1');
        $customer = new Customer('Test');
        $origin = new CustomerLocation($customer, 'Warehouse', 'Calle Test');
        // No lat/lng set

        $result = $this->mapper->mapVehicles([$vehicle], $origin);

        $vv = $result['vroomVehicles'][0];
        self::assertArrayNotHasKey('start', $vv);
    }

    #[Test]
    public function mapVehiclesIncludesSkills(): void
    {
        $vehicle = new Vehicle('Van 1');
        $vehicle->setSkills([VehicleSkill::REFRIGERATED, VehicleSkill::HAZMAT]);

        $result = $this->mapper->mapVehicles([$vehicle], null);

        $vv = $result['vroomVehicles'][0];
        self::assertSame([1, 4], $vv['skills']); // VehicleSkill int values
    }

    #[Test]
    public function mapVehiclesOmitsEmptySkills(): void
    {
        $vehicle = new Vehicle('Van 1');

        $result = $this->mapper->mapVehicles([$vehicle], null);

        self::assertArrayNotHasKey('skills', $result['vroomVehicles'][0]);
    }

    #[Test]
    public function mapVehiclesIncludesMaxTasksWhenProvided(): void
    {
        $vehicle = new Vehicle('Van 1');

        $result = $this->mapper->mapVehicles([$vehicle], null, 15);

        self::assertSame(15, $result['vroomVehicles'][0]['max_tasks']);
    }

    #[Test]
    public function mapVehiclesOmitsMaxTasksWhenNull(): void
    {
        $vehicle = new Vehicle('Van 1');

        $result = $this->mapper->mapVehicles([$vehicle], null);

        self::assertArrayNotHasKey('max_tasks', $result['vroomVehicles'][0]);
    }

    #[Test]
    public function mapVehiclesBuildVehicleMap(): void
    {
        $v1 = new Vehicle('Van 1');
        $v2 = new Vehicle('Van 2');

        $result = $this->mapper->mapVehicles([$v1, $v2], null);

        self::assertSame($v1, $result['vehicleMap'][0]);
        self::assertSame($v2, $result['vehicleMap'][1]);
    }

    #[Test]
    public function mapJobsConvertsShipmentToJob(): void
    {
        $customer = new Customer('Test');
        $shipment = new Shipment('REF-001', $customer);
        $shipment->setLatitude(40.4168);
        $shipment->setLongitude(-3.7038);
        $shipment->setTotalWeightKg(10.0);
        $shipment->setTotalVolumeM3(0.05);
        $shipment->setTotalParcels(3);

        $result = $this->mapper->mapJobs([$shipment]);

        $job = $result['vroomJobs'][0];
        self::assertSame([-3.7038, 40.4168], $job['location']); // [lng, lat]
        self::assertSame([10_000, 50_000, 3], $job['amount']);
        self::assertSame(VroomRequestMapper::DEFAULT_SERVICE_TIME_SECONDS, $job['service']);
        self::assertSame(ShipmentPriority::NORMAL->toVroomPriority(), $job['priority']);
    }

    #[Test]
    public function mapJobsUsesCustomServiceTime(): void
    {
        $customer = new Customer('Test');
        $shipment = new Shipment('REF-001', $customer);
        $shipment->setLatitude(40.0);
        $shipment->setLongitude(-3.0);
        $shipment->setServiceTimeSeconds(600);

        $result = $this->mapper->mapJobs([$shipment]);

        self::assertSame(600, $result['vroomJobs'][0]['service']);
    }

    #[Test]
    public function mapJobsSkipsShipmentsWithoutCoordinates(): void
    {
        $customer = new Customer('Test');
        $noCoords = new Shipment('REF-001', $customer);
        $withCoords = new Shipment('REF-002', $customer);
        $withCoords->setLatitude(40.0);
        $withCoords->setLongitude(-3.0);

        $result = $this->mapper->mapJobs([$noCoords, $withCoords]);

        self::assertCount(1, $result['vroomJobs']);
        self::assertSame($withCoords, $result['shipmentMap'][1]);
    }

    #[Test]
    public function mapJobsIncludesTimeWindows(): void
    {
        $customer = new Customer('Test');
        $shipment = new Shipment('REF-001', $customer);
        $shipment->setLatitude(40.0);
        $shipment->setLongitude(-3.0);
        $shipment->setPreferredWindowStart(new \DateTimeImmutable('09:00:00'));
        $shipment->setPreferredWindowEnd(new \DateTimeImmutable('14:30:00'));

        $result = $this->mapper->mapJobs([$shipment]);

        $job = $result['vroomJobs'][0];
        self::assertArrayHasKey('time_windows', $job);
        self::assertSame([[32400, 52200]], $job['time_windows']); // 9*3600, 14*3600+30*60
    }

    #[Test]
    public function mapJobsOmitsTimeWindowsWhenNotSet(): void
    {
        $customer = new Customer('Test');
        $shipment = new Shipment('REF-001', $customer);
        $shipment->setLatitude(40.0);
        $shipment->setLongitude(-3.0);

        $result = $this->mapper->mapJobs([$shipment]);

        self::assertArrayNotHasKey('time_windows', $result['vroomJobs'][0]);
    }

    #[Test]
    public function mapJobsIncludesRequiredSkills(): void
    {
        $customer = new Customer('Test');
        $shipment = new Shipment('REF-001', $customer);
        $shipment->setLatitude(40.0);
        $shipment->setLongitude(-3.0);
        $shipment->setRequiredSkills([VehicleSkill::FRAGILE]);

        $result = $this->mapper->mapJobs([$shipment]);

        self::assertSame([5], $result['vroomJobs'][0]['skills']);
    }

    #[Test]
    public function mapJobsOmitsEmptySkills(): void
    {
        $customer = new Customer('Test');
        $shipment = new Shipment('REF-001', $customer);
        $shipment->setLatitude(40.0);
        $shipment->setLongitude(-3.0);

        $result = $this->mapper->mapJobs([$shipment]);

        self::assertArrayNotHasKey('skills', $result['vroomJobs'][0]);
    }

    #[Test]
    public function mapJobsHandlesNullWeightAndVolume(): void
    {
        $customer = new Customer('Test');
        $shipment = new Shipment('REF-001', $customer);
        $shipment->setLatitude(40.0);
        $shipment->setLongitude(-3.0);
        // weight and volume are null by default

        $result = $this->mapper->mapJobs([$shipment]);

        $job = $result['vroomJobs'][0];
        self::assertSame([999999, 999999, 1], $job['amount']); // defaults + 1 parcel
    }

    #[Test]
    public function mapJobsPropagatesPriority(): void
    {
        $customer = new Customer('Test');
        $shipment = new Shipment('REF-001', $customer);
        $shipment->setLatitude(40.0);
        $shipment->setLongitude(-3.0);
        $shipment->setPriority(ShipmentPriority::URGENT);

        $result = $this->mapper->mapJobs([$shipment]);

        self::assertSame(75, $result['vroomJobs'][0]['priority']);
    }
}
