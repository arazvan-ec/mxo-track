<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Customer;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\Shipment;
use App\Entity\Vehicle;
use App\Service\RouteCapacityValidator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(RouteCapacityValidator::class)]
final class RouteCapacityValidatorTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private RouteCapacityValidator $validator;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->validator = new RouteCapacityValidator($this->em);
    }

    private function mockStops(Route $route, array $stops): void
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn($stops);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->em->method('createQueryBuilder')->willReturn($qb);
    }

    private function createStopWithShipment(Route $route, int $seq, float $weight, float $volume, int $parcels): RouteStop
    {
        $customer = new Customer('Test');
        $shipment = new Shipment('REF-' . $seq, $customer);
        $shipment->setTotalWeightKg($weight);
        $shipment->setTotalVolumeM3($volume);
        $shipment->setTotalParcels($parcels);

        $stop = new RouteStop($route, $seq, 'Address ' . $seq);
        $stop->setShipment($shipment);

        return $stop;
    }

    #[Test]
    public function validRouteWithinCapacity(): void
    {
        $vehicle = new Vehicle('Van');
        $vehicle->setMaxWeightKg(100.0);
        $vehicle->setMaxVolumeM3(5.0);
        $vehicle->setMaxParcels(10);

        $route = new Route('Test');
        $route->setVehicle($vehicle);

        $stop = $this->createStopWithShipment($route, 1, 25.0, 1.0, 3);
        $this->mockStops($route, [$stop]);

        $result = $this->validator->validate($route);

        self::assertTrue($result['valid']);
        self::assertEmpty($result['errors']);
        self::assertSame(25.0, $result['totalWeightKg']);
        self::assertSame(1.0, $result['totalVolumeM3']);
        self::assertSame(3, $result['totalParcels']);
    }

    #[Test]
    public function weightExceedsCapacity(): void
    {
        $vehicle = new Vehicle('Van');
        $vehicle->setMaxWeightKg(50.0);

        $route = new Route('Test');
        $route->setVehicle($vehicle);

        $stop = $this->createStopWithShipment($route, 1, 75.0, 0.0, 1);
        $this->mockStops($route, [$stop]);

        $result = $this->validator->validate($route);

        self::assertFalse($result['valid']);
        self::assertCount(1, $result['errors']);
        self::assertStringContainsString('Peso total', $result['errors'][0]);
    }

    #[Test]
    public function volumeExceedsCapacity(): void
    {
        $vehicle = new Vehicle('Van');
        $vehicle->setMaxVolumeM3(2.0);

        $route = new Route('Test');
        $route->setVehicle($vehicle);

        $stop = $this->createStopWithShipment($route, 1, 0.0, 3.0, 1);
        $this->mockStops($route, [$stop]);

        $result = $this->validator->validate($route);

        self::assertFalse($result['valid']);
        self::assertStringContainsString('Volumen total', $result['errors'][0]);
    }

    #[Test]
    public function parcelsExceedCapacity(): void
    {
        $vehicle = new Vehicle('Van');
        $vehicle->setMaxParcels(5);

        $route = new Route('Test');
        $route->setVehicle($vehicle);

        $stop = $this->createStopWithShipment($route, 1, 0.0, 0.0, 10);
        $this->mockStops($route, [$stop]);

        $result = $this->validator->validate($route);

        self::assertFalse($result['valid']);
        self::assertStringContainsString('bultos', $result['errors'][0]);
    }

    #[Test]
    public function noVehicleAssigned(): void
    {
        $route = new Route('Test');

        $this->mockStops($route, []);

        $result = $this->validator->validate($route);

        self::assertFalse($result['valid']);
        self::assertStringContainsString('no tiene vehículo', $result['errors'][0]);
    }

    #[Test]
    public function shipmentWithoutWeightNorVolumeGeneratesWarning(): void
    {
        $vehicle = new Vehicle('Van');
        $vehicle->setMaxWeightKg(100.0);

        $route = new Route('Test');
        $route->setVehicle($vehicle);

        $customer = new Customer('Test');
        $shipment = new Shipment('REF-1', $customer);

        $stop = new RouteStop($route, 1, 'Address');
        $stop->setShipment($shipment);

        $this->mockStops($route, [$stop]);

        $result = $this->validator->validate($route);

        self::assertFalse($result['valid']);
        self::assertStringContainsString('no tiene peso ni volumen', $result['errors'][0]);
    }

    #[Test]
    public function utilizationCalculation(): void
    {
        $vehicle = new Vehicle('Van');
        $vehicle->setMaxWeightKg(200.0);
        $vehicle->setMaxVolumeM3(10.0);
        $vehicle->setMaxParcels(20);

        $route = new Route('Test');
        $route->setVehicle($vehicle);

        $stop = $this->createStopWithShipment($route, 1, 100.0, 5.0, 10);
        $this->mockStops($route, [$stop]);

        $result = $this->validator->validate($route);

        self::assertSame(50.0, $result['weightUtilization']);
        self::assertSame(50.0, $result['volumeUtilization']);
        self::assertSame(50.0, $result['parcelUtilization']);
    }

    #[Test]
    public function utilizationNullWhenVehicleCapacityNull(): void
    {
        $vehicle = new Vehicle('Van');

        $route = new Route('Test');
        $route->setVehicle($vehicle);

        $this->mockStops($route, []);

        $result = $this->validator->validate($route);

        self::assertNull($result['weightUtilization']);
        self::assertNull($result['volumeUtilization']);
        self::assertNull($result['parcelUtilization']);
    }

    #[Test]
    public function setsRouteTotals(): void
    {
        $vehicle = new Vehicle('Van');
        $vehicle->setMaxWeightKg(500.0);

        $route = new Route('Test');
        $route->setVehicle($vehicle);

        $stop1 = $this->createStopWithShipment($route, 1, 10.0, 0.5, 2);
        $stop2 = $this->createStopWithShipment($route, 2, 20.0, 1.0, 3);
        $this->mockStops($route, [$stop1, $stop2]);

        $this->validator->validate($route);

        self::assertSame(30.0, $route->getTotalWeightKg());
        self::assertSame(1.5, $route->getTotalVolumeM3());
        self::assertSame(5, $route->getTotalParcels());
    }

    #[Test]
    public function stopWithoutShipmentIsSkipped(): void
    {
        $vehicle = new Vehicle('Van');
        $vehicle->setMaxWeightKg(100.0);

        $route = new Route('Test');
        $route->setVehicle($vehicle);

        $stopNoShipment = new RouteStop($route, 1, 'Address');
        $this->mockStops($route, [$stopNoShipment]);

        $result = $this->validator->validate($route);

        self::assertTrue($result['valid']);
        self::assertSame(0.0, $result['totalWeightKg']);
    }

    #[Test]
    public function canFitShipmentReturnsTrueWhenFits(): void
    {
        $vehicle = new Vehicle('Van');
        $vehicle->setMaxWeightKg(100.0);
        $vehicle->setMaxVolumeM3(5.0);
        $vehicle->setMaxParcels(10);

        $customer = new Customer('Test');
        $shipment = new Shipment('REF-1', $customer);
        $shipment->setTotalWeightKg(20.0);
        $shipment->setTotalVolumeM3(1.0);
        $shipment->setTotalParcels(3);

        self::assertTrue($this->validator->canFitShipment($vehicle, $shipment, 50.0, 2.0, 5));
    }

    #[Test]
    public function canFitShipmentReturnsFalseWhenWeightExceeds(): void
    {
        $vehicle = new Vehicle('Van');
        $vehicle->setMaxWeightKg(100.0);

        $customer = new Customer('Test');
        $shipment = new Shipment('REF-1', $customer);
        $shipment->setTotalWeightKg(60.0);

        self::assertFalse($this->validator->canFitShipment($vehicle, $shipment, 50.0));
    }

    #[Test]
    public function canFitShipmentReturnsFalseWhenVolumeExceeds(): void
    {
        $vehicle = new Vehicle('Van');
        $vehicle->setMaxVolumeM3(5.0);

        $customer = new Customer('Test');
        $shipment = new Shipment('REF-1', $customer);
        $shipment->setTotalVolumeM3(3.0);

        self::assertFalse($this->validator->canFitShipment($vehicle, $shipment, 0.0, 3.0));
    }

    #[Test]
    public function canFitShipmentReturnsFalseWhenParcelsExceed(): void
    {
        $vehicle = new Vehicle('Van');
        $vehicle->setMaxParcels(10);

        $customer = new Customer('Test');
        $shipment = new Shipment('REF-1', $customer);
        $shipment->setTotalParcels(6);

        self::assertFalse($this->validator->canFitShipment($vehicle, $shipment, 0.0, 0.0, 5));
    }

    #[Test]
    public function canFitShipmentNullCapacityMeansNoLimit(): void
    {
        $vehicle = new Vehicle('Van');

        $customer = new Customer('Test');
        $shipment = new Shipment('REF-1', $customer);
        $shipment->setTotalWeightKg(9999.0);
        $shipment->setTotalVolumeM3(9999.0);
        $shipment->setTotalParcels(9999);

        self::assertTrue($this->validator->canFitShipment($vehicle, $shipment));
    }
}
