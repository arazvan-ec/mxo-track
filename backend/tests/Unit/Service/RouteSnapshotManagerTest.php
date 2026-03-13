<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Route;
use App\Entity\RouteSnapshot;
use App\Entity\RouteStop;
use App\Repository\RouteSnapshotRepository;
use App\Routing\Coordinate;
use App\Routing\MultiWaypointRouteResult;
use App\Routing\RoutingEngineInterface;
use App\Service\RouteCapacityValidator;
use App\Service\RouteSnapshotManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RouteSnapshotManager::class)]
final class RouteSnapshotManagerTest extends TestCase
{
    private RouteSnapshotManager $manager;
    private EntityManagerInterface $em;
    private RoutingEngineInterface $routingEngine;
    private RouteCapacityValidator $capacityValidator;
    private RouteSnapshotRepository $snapshotRepo;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->routingEngine = $this->createMock(RoutingEngineInterface::class);
        $this->capacityValidator = $this->createMock(RouteCapacityValidator::class);
        $this->snapshotRepo = $this->createMock(RouteSnapshotRepository::class);

        $this->manager = new RouteSnapshotManager(
            $this->em,
            $this->routingEngine,
            $this->capacityValidator,
            $this->snapshotRepo,
        );
    }

    private function mockStopsQuery(array $stops): void
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn($stops);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);
        $this->em->method('createQueryBuilder')->willReturn($qb);
    }

    #[Test]
    public function createSnapshotPersistsNewSnapshot(): void
    {
        $route = new Route('Test Route');
        $this->snapshotRepo->method('findByRoute')->willReturn(null);
        $this->mockStopsQuery([]);
        $this->capacityValidator->method('validate')->willReturn([
            'valid' => true, 'errors' => [], 'totalWeightKg' => 0.0,
            'totalVolumeM3' => 0.0, 'totalParcels' => 0,
            'weightUtilization' => null, 'volumeUtilization' => null,
            'parcelUtilization' => null,
        ]);

        $this->em->expects(self::once())->method('persist')
            ->with(self::isInstanceOf(RouteSnapshot::class));

        $snapshot = $this->manager->createSnapshot($route);

        self::assertInstanceOf(RouteSnapshot::class, $snapshot);
        self::assertSame($route, $snapshot->getRoute());
    }

    #[Test]
    public function createSnapshotWithMetricsSetsValues(): void
    {
        $route = new Route('Test Route');
        $this->snapshotRepo->method('findByRoute')->willReturn(null);
        $this->mockStopsQuery([]);
        $this->capacityValidator->method('validate')->willReturn([
            'valid' => true, 'errors' => [], 'totalWeightKg' => 0.0,
            'totalVolumeM3' => 0.0, 'totalParcels' => 0,
            'weightUtilization' => null, 'volumeUtilization' => null,
            'parcelUtilization' => null,
        ]);
        $this->em->method('persist');

        $snapshot = $this->manager->createSnapshot(
            $route,
            distanceBeforeKm: 50.0,
            distanceAfterKm: 35.0,
        );

        self::assertSame(50.0, $snapshot->getDistanceBeforeKm());
        self::assertSame(35.0, $snapshot->getDistanceAfterKm());
        self::assertSame(30.0, $snapshot->getSavingsPercent());
    }

    #[Test]
    public function createSnapshotReusesExistingSnapshot(): void
    {
        $route = new Route('Test Route');
        $existing = new RouteSnapshot($route);
        $existing->setPolyline('old_polyline');

        $this->snapshotRepo->method('findByRoute')->willReturn($existing);
        $this->mockStopsQuery([]);
        $this->capacityValidator->method('validate')->willReturn([
            'valid' => true, 'errors' => [], 'totalWeightKg' => 0.0,
            'totalVolumeM3' => 0.0, 'totalParcels' => 0,
            'weightUtilization' => null, 'volumeUtilization' => null,
            'parcelUtilization' => null,
        ]);

        $this->em->expects(self::never())->method('persist');

        $snapshot = $this->manager->createSnapshot($route);

        self::assertSame($existing, $snapshot);
    }

    #[Test]
    public function createSnapshotWithWaypointsCallsOsrm(): void
    {
        $route = new Route('Test Route');
        $this->snapshotRepo->method('findByRoute')->willReturn(null);

        $stop1 = new RouteStop($route, 0, 'Origin');
        $stop1->setOrigin(true);
        $stop1->setLatitude(40.0);
        $stop1->setLongitude(-3.0);

        $stop2 = new RouteStop($route, 1, 'Stop A');
        $stop2->setLatitude(40.1);
        $stop2->setLongitude(-3.1);

        $this->mockStopsQuery([$stop1, $stop2]);

        $routeResult = new MultiWaypointRouteResult(
            totalDistanceKm: 15.5,
            totalDurationSeconds: 900.0,
            legs: [],
            geometry: 'encoded_polyline_from_osrm',
        );
        $this->routingEngine->method('routeWithWaypoints')->willReturn($routeResult);

        $this->capacityValidator->method('validate')->willReturn([
            'valid' => true, 'errors' => [], 'totalWeightKg' => 0.0,
            'totalVolumeM3' => 0.0, 'totalParcels' => 0,
            'weightUtilization' => null, 'volumeUtilization' => null,
            'parcelUtilization' => null,
        ]);
        $this->em->method('persist');

        $snapshot = $this->manager->createSnapshot($route);

        self::assertSame('encoded_polyline_from_osrm', $snapshot->getPolyline());
        self::assertSame(15, $snapshot->getDrivingTimeMinutes());
        self::assertSame(5, $snapshot->getDeliveryTimeMinutes()); // 1 delivery stop * 5 min
        self::assertSame(20, $snapshot->getTotalTimeMinutes());
    }

    #[Test]
    public function updateStopStatesBuildStatesFromStops(): void
    {
        $route = new Route('Test Route');
        $snapshot = new RouteSnapshot($route);
        $this->snapshotRepo->method('findByRoute')->willReturn($snapshot);

        $stop1 = new RouteStop($route, 0, 'Origin');
        $stop1->setOrigin(true);
        $stop1->setLatitude(40.0);
        $stop1->setLongitude(-3.0);

        $stop2 = new RouteStop($route, 1, 'Stop A');
        $stop2->setLatitude(40.1);
        $stop2->setLongitude(-3.1);

        $stop3 = new RouteStop($route, 2, 'Stop B');
        $stop3->setLatitude(40.2);
        $stop3->setLongitude(-3.2);
        $stop3->markDelivered();

        $this->mockStopsQuery([$stop1, $stop2, $stop3]);

        $result = $this->manager->updateStopStates($route);

        self::assertSame($snapshot, $result);
        $states = $result->getStopStates();
        self::assertNotNull($states);
        self::assertCount(3, $states);
        self::assertTrue($states[0]['isOrigin']);
        self::assertSame('PENDING', $states[1]['status']);
        self::assertSame('DELIVERED', $states[2]['status']);
        self::assertArrayHasKey('deliveredAt', $states[2]);
    }

    #[Test]
    public function updateStopStatesCreatesSnapshotIfMissing(): void
    {
        $route = new Route('Test Route');
        $this->snapshotRepo->method('findByRoute')->willReturn(null);
        $this->mockStopsQuery([]);

        $this->em->expects(self::once())->method('persist')
            ->with(self::isInstanceOf(RouteSnapshot::class));

        $result = $this->manager->updateStopStates($route);

        self::assertInstanceOf(RouteSnapshot::class, $result);
        self::assertSame([], $result->getStopStates());
    }

    #[Test]
    public function createSnapshotSetsOriginalStopOrder(): void
    {
        $route = new Route('Test Route');
        $this->snapshotRepo->method('findByRoute')->willReturn(null);
        $this->mockStopsQuery([]);
        $this->capacityValidator->method('validate')->willReturn([
            'valid' => true, 'errors' => [], 'totalWeightKg' => 0.0,
            'totalVolumeM3' => 0.0, 'totalParcels' => 0,
            'weightUtilization' => null, 'volumeUtilization' => null,
            'parcelUtilization' => null,
        ]);
        $this->em->method('persist');

        $originalOrder = [
            ['sequence' => 0, 'address' => 'Origin', 'isOrigin' => true],
            ['sequence' => 1, 'address' => 'Stop A', 'isOrigin' => false],
        ];

        $snapshot = $this->manager->createSnapshot($route, originalStopOrder: $originalOrder);

        self::assertSame($originalOrder, $snapshot->getOriginalStopOrder());
    }
}
