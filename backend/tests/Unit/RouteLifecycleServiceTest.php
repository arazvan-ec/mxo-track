<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Application\Route\InspectionNotCompletedException;
use App\Application\Route\RouteLifecycleService;
use App\Application\Route\RouteNotFoundException;
use App\Application\Route\RouteNotOwnedException;
use App\Domain\Route\Model\Route;
use App\Domain\Route\Repository\RouteRepositoryInterface;
use App\Domain\Route\ValueObject\RouteId;
use App\Entity\User;
use App\Entity\VehicleInspection;
use App\Enum\RouteStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[CoversClass(RouteLifecycleService::class)]
final class RouteLifecycleServiceTest extends TestCase
{
    private RouteRepositoryInterface&MockObject $routeRepo;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private EntityManagerInterface&MockObject $em;
    private RouteLifecycleService $service;

    protected function setUp(): void
    {
        $this->routeRepo = $this->createMock(RouteRepositoryInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->service = new RouteLifecycleService(
            $this->routeRepo,
            $this->eventDispatcher,
            $this->em,
        );
    }

    #[Test]
    public function startRouteTransitionsPlannedToActive(): void
    {
        $routePublicId = '01J0000000000000000000TEST';
        $driver = $this->createDriver('1');
        $route = $this->createDomainRoute($routePublicId, (int) $driver->getId());

        $this->routeRepo->method('findById')->willReturn($route);

        $inspection = $this->createMock(VehicleInspection::class);
        $inspection->method('allItemsChecked')->willReturn(true);
        $this->mockInspectionQuery($inspection);

        $this->routeRepo->expects(self::once())->method('save');
        $this->eventDispatcher->expects(self::once())->method('dispatch');

        $result = $this->service->startRoute($routePublicId, $driver);

        self::assertSame(RouteStatus::ACTIVE, $result->status());
    }

    #[Test]
    public function startRouteThrowsWhenInspectionNotCompleted(): void
    {
        $routePublicId = '01J0000000000000000000TEST';
        $driver = $this->createDriver('1');
        $route = $this->createDomainRoute($routePublicId, (int) $driver->getId());

        $this->routeRepo->method('findById')->willReturn($route);

        $inspection = $this->createMock(VehicleInspection::class);
        $inspection->method('allItemsChecked')->willReturn(false);
        $this->mockInspectionQuery($inspection);

        self::expectException(InspectionNotCompletedException::class);

        $this->service->startRoute($routePublicId, $driver);
    }

    #[Test]
    public function startRouteThrowsWhenNoInspection(): void
    {
        $routePublicId = '01J0000000000000000000TEST';
        $driver = $this->createDriver('1');
        $route = $this->createDomainRoute($routePublicId, (int) $driver->getId());

        $this->routeRepo->method('findById')->willReturn($route);
        $this->mockInspectionQuery(null);

        self::expectException(InspectionNotCompletedException::class);

        $this->service->startRoute($routePublicId, $driver);
    }

    #[Test]
    public function finishRouteTransitionsActiveToDone(): void
    {
        $routePublicId = '01J0000000000000000000TEST';
        $driver = $this->createDriver('1');
        $route = $this->createDomainRoute($routePublicId, (int) $driver->getId(), RouteStatus::ACTIVE);

        $this->routeRepo->method('findById')->willReturn($route);

        $this->routeRepo->expects(self::once())->method('save');
        $this->eventDispatcher->expects(self::once())->method('dispatch');

        $result = $this->service->finishRoute($routePublicId, $driver);

        self::assertSame(RouteStatus::DONE, $result->status());
    }

    #[Test]
    public function startRouteThrowsWhenRouteNotFound(): void
    {
        $driver = $this->createDriver('1');
        $this->routeRepo->method('findById')->willReturn(null);

        self::expectException(RouteNotFoundException::class);

        $this->service->startRoute('01NONEXISTENT00000000000000', $driver);
    }

    #[Test]
    public function startRouteThrowsWhenDriverNotOwner(): void
    {
        $routePublicId = '01J0000000000000000000TEST';
        $driver = $this->createDriver('1');
        $route = $this->createDomainRoute($routePublicId, 999); // different driver

        $this->routeRepo->method('findById')->willReturn($route);

        self::expectException(RouteNotOwnedException::class);

        $this->service->startRoute($routePublicId, $driver);
    }

    #[Test]
    public function finishRouteThrowsWhenRouteNotFound(): void
    {
        $driver = $this->createDriver('1');
        $this->routeRepo->method('findById')->willReturn(null);

        self::expectException(RouteNotFoundException::class);

        $this->service->finishRoute('01NONEXISTENT00000000000000', $driver);
    }

    private function createDriver(string $id): User&MockObject
    {
        $driver = $this->createMock(User::class);
        $driver->method('getId')->willReturn($id);

        return $driver;
    }

    private function createDomainRoute(string $publicId, int $driverId, RouteStatus $status = RouteStatus::PLANNED): Route
    {
        return Route::reconstitute(
            id: new RouteId($publicId),
            name: 'Test Route',
            status: $status,
            driverId: $driverId,
            vehicleId: null,
            customerId: null,
            originLocationId: null,
            capacity: null,
            totalDistance: null,
            estimatedDurationMinutes: null,
            aiAnalysis: null,
            autoReoptimize: false,
            startAt: null,
            endAt: null,
            deletedAt: null,
        );
    }

    private function mockInspectionQuery(?VehicleInspection $result): void
    {
        $query = $this->getMockBuilder(\Doctrine\ORM\Query::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getOneOrNullResult'])
            ->getMock();
        $query->method('getOneOrNullResult')->willReturn($result);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('join')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->em->method('createQueryBuilder')->willReturn($qb);
    }
}
