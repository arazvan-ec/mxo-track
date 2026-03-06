<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Customer;
use App\Entity\CustomerLocation;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\Shipment;
use App\Entity\Vehicle;
use App\Service\RouteCapacityValidator;
use App\Service\VroomResponseMapper;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(VroomResponseMapper::class)]
final class VroomResponseMapperTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private RouteCapacityValidator $capacityValidator;
    private VroomResponseMapper $mapper;
    private Customer $customer;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);

        // RouteCapacityValidator is final, so we create a real one with a mocked EM
        // that returns empty stops (so validation always passes)
        $validatorEm = $this->createMock(EntityManagerInterface::class);
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([]);
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);
        $validatorEm->method('createQueryBuilder')->willReturn($qb);

        $this->capacityValidator = new RouteCapacityValidator($validatorEm);
        $this->mapper = new VroomResponseMapper($this->em, $this->capacityValidator);
        $this->customer = new Customer('Test Customer');
    }

    #[Test]
    public function mapsVroomRouteToRouteEntity(): void
    {
        $vehicle = new Vehicle('Van 1');
        $shipment = new Shipment('REF-001', $this->customer);
        $shipment->setAddress('Calle Mayor 1');
        $shipment->setLatitude(40.42);
        $shipment->setLongitude(-3.70);
        $shipment->setRecipientName('Juan');
        $shipment->setRecipientPhone('612345678');

        $this->em->expects(self::atLeastOnce())->method('persist');

        $vroomResponse = [
            'routes' => [[
                'vehicle' => 0,
                'distance' => 15000,
                'duration' => 1800,
                'steps' => [
                    ['type' => 'start'],
                    ['type' => 'job', 'id' => 0],
                    ['type' => 'end'],
                ],
            ]],
            'unassigned' => [],
        ];

        $result = $this->mapper->mapToRoutes(
            $vroomResponse, [0 => $vehicle], [0 => $shipment], $this->customer, null,
        );

        self::assertCount(1, $result['routes']);
        $routeData = $result['routes'][0];
        self::assertInstanceOf(Route::class, $routeData['route']);
        self::assertStringStartsWith('Ruta 1 -', $routeData['route']->getName());
        self::assertSame($vehicle, $routeData['route']->getVehicle());
        self::assertSame($this->customer, $routeData['route']->getCustomer());
        self::assertSame(15.0, $routeData['route']->getTotalDistanceKm());
        self::assertSame(30, $routeData['route']->getEstimatedDurationMinutes());
    }

    #[Test]
    public function mapsDeliveryStopsFromVroomSteps(): void
    {
        $vehicle = new Vehicle('Van 1');
        $shipment = new Shipment('REF-001', $this->customer);
        $shipment->setAddress('Calle Mayor 1');
        $shipment->setLatitude(40.42);
        $shipment->setLongitude(-3.70);
        $shipment->setRecipientName('Juan');

        $this->em->method('persist');

        $vroomResponse = [
            'routes' => [[
                'vehicle' => 0,
                'distance' => 10000,
                'duration' => 600,
                'steps' => [['type' => 'job', 'id' => 0]],
            ]],
        ];

        $result = $this->mapper->mapToRoutes(
            $vroomResponse, [0 => $vehicle], [0 => $shipment], $this->customer, null,
        );

        $stops = $result['routes'][0]['stops'];
        self::assertCount(1, $stops);
        self::assertSame('Calle Mayor 1', $stops[0]->getAddress());
        self::assertSame($shipment, $stops[0]->getShipment());
        self::assertSame('Juan', $stops[0]->getRecipientName());
    }

    #[Test]
    public function addsOriginStopWhenOriginProvided(): void
    {
        $vehicle = new Vehicle('Van 1');
        $origin = new CustomerLocation($this->customer, 'Warehouse', 'Calle Almacen 5');
        $origin->setLatitude(40.41);
        $origin->setLongitude(-3.71);

        $this->em->method('persist');

        $vroomResponse = [
            'routes' => [[
                'vehicle' => 0,
                'distance' => 5000,
                'duration' => 300,
                'steps' => [],
            ]],
        ];

        $result = $this->mapper->mapToRoutes(
            $vroomResponse, [0 => $vehicle], [], $this->customer, $origin,
        );

        $stops = $result['routes'][0]['stops'];
        self::assertCount(1, $stops);
        self::assertTrue($stops[0]->isOrigin());
        self::assertSame('Calle Almacen 5', $stops[0]->getAddress());
        self::assertSame(0, $stops[0]->getSequence());
    }

    #[Test]
    public function collectsUnassignedShipments(): void
    {
        $vehicle = new Vehicle('Van 1');
        $assigned = new Shipment('REF-001', $this->customer);
        $assigned->setAddress('Addr 1');
        $assigned->setLatitude(40.0);
        $assigned->setLongitude(-3.0);

        $unassignedShipment = new Shipment('REF-002', $this->customer);

        $this->em->method('persist');

        $vroomResponse = [
            'routes' => [[
                'vehicle' => 0,
                'distance' => 1000,
                'duration' => 120,
                'steps' => [['type' => 'job', 'id' => 0]],
            ]],
            'unassigned' => [['id' => 1]],
        ];

        $result = $this->mapper->mapToRoutes(
            $vroomResponse, [0 => $vehicle], [0 => $assigned, 1 => $unassignedShipment],
            $this->customer, null,
        );

        self::assertCount(1, $result['unassigned']);
        self::assertSame($unassignedShipment, $result['unassigned'][0]);
    }

    #[Test]
    public function skipsRouteWhenVehicleNotInMap(): void
    {
        $this->em->method('persist');

        $vroomResponse = [
            'routes' => [[
                'vehicle' => 99,
                'distance' => 1000,
                'duration' => 120,
                'steps' => [],
            ]],
        ];

        $result = $this->mapper->mapToRoutes(
            $vroomResponse, [], [], $this->customer, null,
        );

        self::assertCount(0, $result['routes']);
    }

    #[Test]
    public function skipsJobStepWhenShipmentNotInMap(): void
    {
        $vehicle = new Vehicle('Van 1');
        $this->em->method('persist');

        $vroomResponse = [
            'routes' => [[
                'vehicle' => 0,
                'distance' => 1000,
                'duration' => 120,
                'steps' => [['type' => 'job', 'id' => 99]],
            ]],
        ];

        $result = $this->mapper->mapToRoutes(
            $vroomResponse, [0 => $vehicle], [], $this->customer, null,
        );

        self::assertCount(1, $result['routes']);
        self::assertCount(0, $result['routes'][0]['stops']);
    }

    #[Test]
    public function emptyResponseReturnsEmptyRoutes(): void
    {
        $result = $this->mapper->mapToRoutes(
            [], [], [], $this->customer, null,
        );

        self::assertCount(0, $result['routes']);
        self::assertCount(0, $result['unassigned']);
    }

    #[Test]
    public function validationResultIncludedPerRoute(): void
    {
        $vehicle = new Vehicle('Van 1');
        $this->em->method('persist');

        $vroomResponse = [
            'routes' => [[
                'vehicle' => 0,
                'distance' => 1000,
                'duration' => 60,
                'steps' => [],
            ]],
        ];

        $result = $this->mapper->mapToRoutes(
            $vroomResponse, [0 => $vehicle], [], $this->customer, null,
        );

        self::assertArrayHasKey('validation', $result['routes'][0]);
        self::assertArrayHasKey('valid', $result['routes'][0]['validation']);
    }

    #[Test]
    public function shipmentWithNullAddressUsesDefault(): void
    {
        $vehicle = new Vehicle('Van 1');
        $shipment = new Shipment('REF-001', $this->customer);
        $shipment->setLatitude(40.0);
        $shipment->setLongitude(-3.0);

        $this->em->method('persist');

        $vroomResponse = [
            'routes' => [[
                'vehicle' => 0,
                'distance' => 1000,
                'duration' => 60,
                'steps' => [['type' => 'job', 'id' => 0]],
            ]],
        ];

        $result = $this->mapper->mapToRoutes(
            $vroomResponse, [0 => $vehicle], [0 => $shipment], $this->customer, null,
        );

        self::assertSame('Sin dirección', $result['routes'][0]['stops'][0]->getAddress());
    }
}
