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
use App\View\MapViewData;
use App\View\MapViewOptions;
use App\View\RouteViewService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Uid\Ulid;

#[CoversClass(RouteSnapshotListener::class)]
final class RouteSnapshotListenerTest extends TestCase
{
    private RouteSnapshotListener $listener;
    private RouteSnapshotManager $snapshotManager;
    private RouteViewService $viewService;
    private HubInterface $hub;
    private RouteRepository $routeRepo;

    protected function setUp(): void
    {
        $this->snapshotManager = $this->createMock(RouteSnapshotManager::class);
        $this->viewService = $this->createMock(RouteViewService::class);
        $this->hub = $this->createMock(HubInterface::class);
        $this->routeRepo = $this->createMock(RouteRepository::class);

        $this->listener = new RouteSnapshotListener(
            $this->snapshotManager,
            $this->viewService,
            $this->hub,
            $this->routeRepo,
        );
    }

    private function createRouteWithPublicId(string $name): Route
    {
        $route = new Route($name);
        $route->initializePublicId(); // Simulate @PrePersist

        return $route;
    }

    private function stubRouteAndView(Route $route): void
    {
        $this->routeRepo->method('findOneByPublicId')->willReturn($route);

        $snapshot = new RouteSnapshot($route);
        $this->snapshotManager->method('updateStopStates')->willReturn($snapshot);

        $mapView = new MapViewData(routes: [], options: new MapViewOptions());
        $this->viewService->method('buildSingleRouteView')->willReturn($mapView);
    }

    #[Test]
    public function onStopDeliveredUpdatesSnapshotAndPublishes(): void
    {
        $route = $this->createRouteWithPublicId('Test Route');
        $this->stubRouteAndView($route);

        $this->snapshotManager->expects(self::once())->method('updateStopStates')->with($route);
        $this->hub->expects(self::exactly(3))->method('publish')
            ->with(self::isInstanceOf(Update::class));

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
    public function onStopExceptionUpdatesSnapshotAndPublishes(): void
    {
        $route = $this->createRouteWithPublicId('Test Route');
        $this->stubRouteAndView($route);

        $this->snapshotManager->expects(self::once())->method('updateStopStates')->with($route);
        $this->hub->expects(self::exactly(3))->method('publish');

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
    public function onRouteStartedUpdatesSnapshotAndPublishes(): void
    {
        $route = $this->createRouteWithPublicId('Test Route');
        $this->stubRouteAndView($route);

        $this->snapshotManager->expects(self::once())->method('updateStopStates');
        $this->hub->expects(self::exactly(3))->method('publish');

        $event = new RouteStarted(
            routePublicId: 'route1',
            driverUserId: 1,
        );

        $this->listener->onRouteStarted($event);
    }

    #[Test]
    public function onRouteCompletedUpdatesSnapshotAndPublishes(): void
    {
        $route = $this->createRouteWithPublicId('Test Route');
        $this->stubRouteAndView($route);

        $this->snapshotManager->expects(self::once())->method('updateStopStates');
        $this->hub->expects(self::exactly(3))->method('publish');

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
        $this->hub->expects(self::never())->method('publish');

        $event = new StopDelivered(
            stopPublicId: 'stop1',
            shipmentPublicId: 'ship1',
            routePublicId: 'nonexistent',
            driverUserId: 1,
            podPublicId: 'pod1',
        );

        $this->listener->onStopDelivered($event);
    }

    #[Test]
    public function mercureFailureDoesNotBreakFlow(): void
    {
        $route = $this->createRouteWithPublicId('Test Route');
        $this->routeRepo->method('findOneByPublicId')->willReturn($route);

        $snapshot = new RouteSnapshot($route);
        $this->snapshotManager->method('updateStopStates')->willReturn($snapshot);

        $mapView = new MapViewData(routes: [], options: new MapViewOptions());
        $this->viewService->method('buildSingleRouteView')->willReturn($mapView);

        $this->hub->method('publish')->willThrowException(new \RuntimeException('Mercure down'));

        // Should not throw
        $event = new StopDelivered(
            stopPublicId: 'stop1',
            shipmentPublicId: 'ship1',
            routePublicId: 'route1',
            driverUserId: 1,
            podPublicId: 'pod1',
        );

        $this->listener->onStopDelivered($event);

        // If we got here, the exception was caught
        self::assertTrue(true);
    }
}
