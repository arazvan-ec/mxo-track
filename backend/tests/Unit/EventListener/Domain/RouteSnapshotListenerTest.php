<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener\Domain;

use App\Domain\Event\RouteCompleted;
use App\Domain\Event\RouteStarted;
use App\Domain\Event\StopDelivered;
use App\Domain\Event\StopExceptionReported;
use App\Entity\Route;
use App\Entity\RouteSnapshot;
use App\Enum\ExceptionCode;
use App\EventListener\Domain\RouteSnapshotListener;
use App\Repository\RouteRepository;
use App\Service\RouteSnapshotManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RouteSnapshotListener::class)]
final class RouteSnapshotListenerTest extends TestCase
{
    private RouteSnapshotListener $listener;
    private RouteSnapshotManager $snapshotManager;
    private RouteRepository $routeRepo;

    protected function setUp(): void
    {
        $this->snapshotManager = $this->createMock(RouteSnapshotManager::class);
        $this->routeRepo = $this->createMock(RouteRepository::class);

        $this->listener = new RouteSnapshotListener(
            $this->snapshotManager,
            $this->routeRepo,
        );
    }

    private function createRouteWithPublicId(string $name): Route
    {
        $route = new Route($name);
        $route->initializePublicId();

        return $route;
    }

    #[Test]
    public function onStopDeliveredUpdatesSnapshot(): void
    {
        $route = $this->createRouteWithPublicId('Test Route');
        $this->routeRepo->method('findOneByPublicId')->willReturn($route);

        $this->snapshotManager->expects(self::once())->method('updateStopStates')->with($route);

        $event = new StopDelivered(
            stopPublicId: 'stop1',
            shipmentPublicId: 'ship1',
            routePublicId: 'route1',
            driverUserId: 1,
            podPublicId: 'pod1',
        );

        $this->listener->onStopDelivered($event);
    }

    #[Test]
    public function onStopExceptionUpdatesSnapshot(): void
    {
        $route = $this->createRouteWithPublicId('Test Route');
        $this->routeRepo->method('findOneByPublicId')->willReturn($route);

        $this->snapshotManager->expects(self::once())->method('updateStopStates')->with($route);

        $event = new StopExceptionReported(
            stopPublicId: 'stop1',
            shipmentPublicId: 'ship1',
            routePublicId: 'route1',
            driverUserId: 1,
            reason: ExceptionCode::ABSENT,
            notes: 'Nobody home',
        );

        $this->listener->onStopExceptionReported($event);
    }

    #[Test]
    public function onRouteStartedUpdatesSnapshot(): void
    {
        $route = $this->createRouteWithPublicId('Test Route');
        $this->routeRepo->method('findOneByPublicId')->willReturn($route);

        $this->snapshotManager->expects(self::once())->method('updateStopStates');

        $event = new RouteStarted(
            routePublicId: 'route1',
            driverUserId: 1,
        );

        $this->listener->onRouteStarted($event);
    }

    #[Test]
    public function onRouteCompletedUpdatesSnapshot(): void
    {
        $route = $this->createRouteWithPublicId('Test Route');
        $this->routeRepo->method('findOneByPublicId')->willReturn($route);

        $this->snapshotManager->expects(self::once())->method('updateStopStates');

        $event = new RouteCompleted(
            routePublicId: 'route1',
            driverUserId: 1,
        );

        $this->listener->onRouteCompleted($event);
    }

    #[Test]
    public function missingRouteIsNoOp(): void
    {
        $this->routeRepo->method('findOneByPublicId')->willReturn(null);

        $this->snapshotManager->expects(self::never())->method('updateStopStates');

        $event = new StopDelivered(
            stopPublicId: 'stop1',
            shipmentPublicId: 'ship1',
            routePublicId: 'nonexistent',
            driverUserId: 1,
            podPublicId: 'pod1',
        );

        $this->listener->onStopDelivered($event);
    }
}
