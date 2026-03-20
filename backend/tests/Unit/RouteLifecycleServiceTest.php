<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Application\Route\InspectionNotCompletedException;
use App\Application\Route\RouteLifecycleService;
use App\Application\Route\RouteNotFoundException;
use App\Application\Route\RouteNotOwnedException;
use App\Entity\Route;
use App\Entity\User;
use App\Entity\VehicleInspection;
use App\Enum\RouteStatus;
use App\Domain\Route\Repository\RouteRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[CoversClass(RouteLifecycleService::class)]
final class RouteLifecycleServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private RouteRepositoryInterface&MockObject $routeRepo;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private RouteLifecycleService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->routeRepo = $this->createMock(RouteRepositoryInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->service = new RouteLifecycleService(
            $this->em,
            $this->routeRepo,
            $this->eventDispatcher,
        );
    }

    #[Test]
    public function startRouteTransitionsPlannedToActive(): void
    {
        $driver = $this->createDriver('1');
        $route = new Route('Test Route');
        $route->setDriver($driver);
        $this->initPublicId($route);

        $routePublicId = $route->getPublicIdString();
        $this->routeRepo->method('findOneByPublicId')->with($routePublicId)->willReturn($route);

        $inspection = new VehicleInspection($route, $driver, [
            ['name' => 'Tires', 'checked' => true],
            ['name' => 'Brakes', 'checked' => true],
        ]);

        $inspectionRepo = $this->createMock(EntityRepository::class);
        $inspectionRepo->method('findOneBy')->with(['route' => $route])->willReturn($inspection);

        $this->em->method('getRepository')
            ->with(VehicleInspection::class)
            ->willReturn($inspectionRepo);

        $this->em->expects(self::once())->method('flush');
        $this->eventDispatcher->expects(self::once())->method('dispatch');

        $result = $this->service->startRoute($routePublicId, $driver);

        self::assertSame(RouteStatus::ACTIVE, $result->getStatus());
    }

    #[Test]
    public function startRouteThrowsWhenInspectionNotCompleted(): void
    {
        $driver = $this->createDriver('1');
        $route = new Route('Test Route');
        $route->setDriver($driver);
        $this->initPublicId($route);

        $this->routeRepo->method('findOneByPublicId')->willReturn($route);

        $inspection = new VehicleInspection($route, $driver, [
            ['name' => 'Tires', 'checked' => false],
        ]);

        $inspectionRepo = $this->createMock(EntityRepository::class);
        $inspectionRepo->method('findOneBy')->willReturn($inspection);
        $this->em->method('getRepository')->willReturn($inspectionRepo);

        self::expectException(InspectionNotCompletedException::class);

        $this->service->startRoute($route->getPublicIdString(), $driver);
    }

    #[Test]
    public function startRouteThrowsWhenNoInspection(): void
    {
        $driver = $this->createDriver('1');
        $route = new Route('Test Route');
        $route->setDriver($driver);
        $this->initPublicId($route);

        $this->routeRepo->method('findOneByPublicId')->willReturn($route);

        $inspectionRepo = $this->createMock(EntityRepository::class);
        $inspectionRepo->method('findOneBy')->willReturn(null);
        $this->em->method('getRepository')->willReturn($inspectionRepo);

        self::expectException(InspectionNotCompletedException::class);

        $this->service->startRoute($route->getPublicIdString(), $driver);
    }

    #[Test]
    public function finishRouteTransitionsActiveToDone(): void
    {
        $driver = $this->createDriver('1');
        $route = new Route('Test Route');
        $route->setDriver($driver);
        $this->initPublicId($route);

        // Start the route first to get it to ACTIVE state
        $ref = new \ReflectionClass($route);
        $statusProp = $ref->getProperty('status');
        $statusProp->setValue($route, RouteStatus::ACTIVE);

        $routePublicId = $route->getPublicIdString();
        $this->routeRepo->method('findOneByPublicId')->with($routePublicId)->willReturn($route);

        $this->em->expects(self::once())->method('flush');
        $this->eventDispatcher->expects(self::once())->method('dispatch');

        $result = $this->service->finishRoute($routePublicId, $driver);

        self::assertSame(RouteStatus::DONE, $result->getStatus());
    }

    #[Test]
    public function startRouteThrowsWhenRouteNotFound(): void
    {
        $driver = $this->createDriver('1');
        $this->routeRepo->method('findOneByPublicId')->willReturn(null);

        self::expectException(RouteNotFoundException::class);

        $this->service->startRoute('01NONEXISTENT00000000000000', $driver);
    }

    #[Test]
    public function startRouteThrowsWhenDriverNotOwner(): void
    {
        $driver = $this->createDriver('1');
        $otherDriver = $this->createDriver('2');

        $route = new Route('Test Route');
        $route->setDriver($otherDriver);
        $this->initPublicId($route);

        $this->routeRepo->method('findOneByPublicId')->willReturn($route);

        self::expectException(RouteNotOwnedException::class);

        $this->service->startRoute($route->getPublicIdString(), $driver);
    }

    #[Test]
    public function finishRouteThrowsWhenRouteNotFound(): void
    {
        $driver = $this->createDriver('1');
        $this->routeRepo->method('findOneByPublicId')->willReturn(null);

        self::expectException(RouteNotFoundException::class);

        $this->service->finishRoute('01NONEXISTENT00000000000000', $driver);
    }

    private function createDriver(string $id): User&MockObject
    {
        $driver = $this->createMock(User::class);
        $driver->method('getId')->willReturn($id);

        return $driver;
    }

    private function initPublicId(object $entity): void
    {
        $ref = new \ReflectionClass($entity);
        $prop = $ref->getProperty('publicId');
        $prop->setValue($entity, new Ulid());
    }
}
