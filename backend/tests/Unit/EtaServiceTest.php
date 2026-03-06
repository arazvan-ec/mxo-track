<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\Vehicle;
use App\Entity\VehicleLastPosition;
use App\Enum\RouteStopStatus;
use App\Service\EtaService;
use App\Service\OsrmClient;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(EtaService::class)]
final class EtaServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private OsrmClient $osrmClient;
    private EtaService $service;
    private HttpClientInterface&MockObject $httpClient;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->osrmClient = new OsrmClient($this->httpClient, 'http://osrm:5000');
        $this->service = new EtaService($this->em, $this->osrmClient);
    }

    private function mockQueryStops(array $stops): void
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

    private function mockOsrmResponse(float $distanceMeters, float $durationSeconds): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'code' => 'Ok',
            'routes' => [['distance' => $distanceMeters, 'duration' => $durationSeconds]],
        ]);
        $this->httpClient->method('request')->willReturn($response);
    }

    #[Test]
    public function returnsEmptyWhenNoVehicle(): void
    {
        $route = new Route('Test');

        $result = $this->service->calculateEtas($route);

        self::assertSame([], $result);
    }

    #[Test]
    public function returnsEmptyWhenNoPositionAndNoOrigin(): void
    {
        $route = new Route('Test');
        $vehicle = new Vehicle('Van');
        $route->setVehicle($vehicle);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);
        $this->em->method('getRepository')->willReturn($repo);

        $stop = new RouteStop($route, 1, 'Addr');
        $stop->initializePublicId();
        $stop->setLatitude(40.0);
        $stop->setLongitude(-3.0);

        $this->mockQueryStops([$stop]);

        $result = $this->service->calculateEtas($route);

        self::assertSame([], $result);
    }

    #[Test]
    public function usesOriginWhenNoVehiclePosition(): void
    {
        $route = new Route('Test');
        $vehicle = new Vehicle('Van');
        $route->setVehicle($vehicle);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);
        $this->em->method('getRepository')->willReturn($repo);

        $origin = new RouteStop($route, 0, 'Warehouse');
        $origin->initializePublicId();
        $origin->setOrigin(true);
        $origin->setLatitude(40.41);
        $origin->setLongitude(-3.70);

        $stop = new RouteStop($route, 1, 'Delivery');
        $stop->initializePublicId();
        $stop->setLatitude(40.42);
        $stop->setLongitude(-3.71);

        $this->mockQueryStops([$origin, $stop]);
        $this->mockOsrmResponse(2500.0, 600.0); // 2.5km, 600s

        $result = $this->service->calculateEtas($route);

        self::assertCount(1, $result);
        $eta = $result[$stop->getPublicIdString()];
        self::assertSame(10, $eta['remainingMinutes']); // ceil(600/60) = 10
        self::assertSame(2.5, $eta['distanceKm']);
    }

    #[Test]
    public function accumulatesTimeWithDeliveryBuffer(): void
    {
        $route = new Route('Test');
        $vehicle = new Vehicle('Van');
        $route->setVehicle($vehicle);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);
        $this->em->method('getRepository')->willReturn($repo);

        $origin = new RouteStop($route, 0, 'Warehouse');
        $origin->initializePublicId();
        $origin->setOrigin(true);
        $origin->setLatitude(40.41);
        $origin->setLongitude(-3.70);

        $stop1 = new RouteStop($route, 1, 'Stop 1');
        $stop1->initializePublicId();
        $stop1->setLatitude(40.42);
        $stop1->setLongitude(-3.71);

        $stop2 = new RouteStop($route, 2, 'Stop 2');
        $stop2->initializePublicId();
        $stop2->setLatitude(40.43);
        $stop2->setLongitude(-3.72);

        $this->mockQueryStops([$origin, $stop1, $stop2]);
        $this->mockOsrmResponse(1000.0, 300.0); // 1km, 300s per leg

        $result = $this->service->calculateEtas($route);

        self::assertCount(2, $result);
        // Stop 1: 300s = 5 min
        self::assertSame(5, $result[$stop1->getPublicIdString()]['remainingMinutes']);
        // Stop 2: 300s (to stop1) + 120s (delivery) + 300s (to stop2) = 720s = 12 min
        self::assertSame(12, $result[$stop2->getPublicIdString()]['remainingMinutes']);
    }

    #[Test]
    public function skipsDeliveredStopsButUpdatesPosition(): void
    {
        $route = new Route('Test');
        $vehicle = new Vehicle('Van');
        $route->setVehicle($vehicle);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);
        $this->em->method('getRepository')->willReturn($repo);

        $origin = new RouteStop($route, 0, 'Warehouse');
        $origin->initializePublicId();
        $origin->setOrigin(true);
        $origin->setLatitude(40.41);
        $origin->setLongitude(-3.70);

        $delivered = new RouteStop($route, 1, 'Delivered');
        $delivered->initializePublicId();
        $delivered->setLatitude(40.42);
        $delivered->setLongitude(-3.71);
        $delivered->markDelivered();

        $pending = new RouteStop($route, 2, 'Pending');
        $pending->initializePublicId();
        $pending->setLatitude(40.43);
        $pending->setLongitude(-3.72);

        $this->mockQueryStops([$origin, $delivered, $pending]);
        $this->mockOsrmResponse(1000.0, 180.0);

        $result = $this->service->calculateEtas($route);

        self::assertCount(1, $result);
        self::assertArrayHasKey($pending->getPublicIdString(), $result);
    }

    #[Test]
    public function skipsStopsWithoutCoordinates(): void
    {
        $route = new Route('Test');
        $vehicle = new Vehicle('Van');
        $route->setVehicle($vehicle);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);
        $this->em->method('getRepository')->willReturn($repo);

        $origin = new RouteStop($route, 0, 'Warehouse');
        $origin->initializePublicId();
        $origin->setOrigin(true);
        $origin->setLatitude(40.41);
        $origin->setLongitude(-3.70);

        $noCoords = new RouteStop($route, 1, 'No Coords');
        $noCoords->initializePublicId();

        $this->mockQueryStops([$origin, $noCoords]);

        $result = $this->service->calculateEtas($route);

        self::assertCount(0, $result);
    }

    #[Test]
    public function estimateArrivalDelegatesToOsrm(): void
    {
        $this->mockOsrmResponse(100000.0, 3600.0);

        $result = $this->service->estimateArrival(40.0, -3.0, 41.0, -4.0);

        self::assertInstanceOf(\DateTimeImmutable::class, $result);
    }

    #[Test]
    public function returnsEmptyWhenNoStops(): void
    {
        $route = new Route('Test');
        $vehicle = new Vehicle('Van');
        $route->setVehicle($vehicle);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);
        $this->em->method('getRepository')->willReturn($repo);

        $this->mockQueryStops([]);

        $result = $this->service->calculateEtas($route);

        self::assertSame([], $result);
    }
}
