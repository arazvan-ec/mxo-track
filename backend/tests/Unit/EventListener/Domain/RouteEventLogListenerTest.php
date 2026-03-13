<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener\Domain;

use App\Domain\Event\RouteAssigned;
use App\Domain\Event\RouteCancelled;
use App\Domain\Event\RouteCompleted;
use App\Domain\Event\RouteOptimized;
use App\Domain\Event\RouteStarted;
use App\Domain\Event\RoutesBuilt;
use App\Domain\Event\StopDelivered;
use App\Domain\Event\StopExceptionReported;
use App\Entity\Route;
use App\Entity\RouteEvent;
use App\Entity\User;
use App\Enum\ExceptionCode;
use App\Enum\RouteEventType;
use App\EventListener\Domain\RouteEventLogListener;
use App\Repository\RouteRepository;
use App\Repository\RouteStopRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RouteEventLogListener::class)]
final class RouteEventLogListenerTest extends TestCase
{
    private RouteEventLogListener $listener;
    private EntityManagerInterface $em;
    private RouteRepository $routeRepo;
    private UserRepository $userRepo;
    private RouteStopRepository $stopRepo;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->routeRepo = $this->createMock(RouteRepository::class);
        $this->userRepo = $this->createMock(UserRepository::class);
        $this->stopRepo = $this->createMock(RouteStopRepository::class);

        $this->stopRepo->method('findBy')->willReturn([]);

        $this->listener = new RouteEventLogListener(
            $this->em,
            $this->routeRepo,
            $this->userRepo,
            $this->stopRepo,
        );
    }

    private function createRoute(string $name = 'Test Route'): Route
    {
        $route = new Route($name);
        $route->initializePublicId();

        return $route;
    }

    private function stubRoute(Route $route): void
    {
        $this->routeRepo->method('findOneByPublicId')->willReturn($route);
    }

    #[Test]
    public function onRoutesBuiltCreatesCreatedEventPerRoute(): void
    {
        $route = $this->createRoute();
        $this->stubRoute($route);

        $persisted = [];
        $this->em->expects(self::exactly(2))
            ->method('persist')
            ->willReturnCallback(function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            });
        $this->em->expects(self::once())->method('flush');

        $event = new RoutesBuilt(
            routePublicIds: ['route1', 'route2'],
            shipmentCount: 10,
            vehicleCount: 2,
        );

        $this->listener->onRoutesBuilt($event);

        self::assertCount(2, $persisted);
        self::assertInstanceOf(RouteEvent::class, $persisted[0]);
        self::assertSame(RouteEventType::CREATED, $persisted[0]->getEventType());
        self::assertSame('system', $persisted[0]->getActorType());
        self::assertSame(10, $persisted[0]->getPayload()['shipment_count']);
    }

    #[Test]
    public function onRouteOptimizedCreatesOptimizedEvent(): void
    {
        $route = $this->createRoute();
        $this->stubRoute($route);

        $this->em->expects(self::once())
            ->method('persist')
            ->with(self::callback(function (RouteEvent $e): bool {
                return $e->getEventType() === RouteEventType::OPTIMIZED
                    && $e->getActorType() === 'system'
                    && $e->getPayload()['improvement_percent'] === 14.9;
            }));
        $this->em->expects(self::once())->method('flush');

        $event = new RouteOptimized(
            routePublicId: 'route1',
            improvementPercent: 14.9,
            distanceKm: 25.3,
            durationMinutes: 45,
        );

        $this->listener->onRouteOptimized($event);
    }

    #[Test]
    public function onRouteStartedCreatesStartedEventWithDriverActor(): void
    {
        $route = $this->createRoute();
        $this->stubRoute($route);

        $driver = $this->createMock(User::class);
        $this->userRepo->method('find')->with(42)->willReturn($driver);

        $this->em->expects(self::once())
            ->method('persist')
            ->with(self::callback(function (RouteEvent $e) use ($driver): bool {
                return $e->getEventType() === RouteEventType::STARTED
                    && $e->getActorType() === 'driver'
                    && $e->getActorUser() === $driver;
            }));

        $event = new RouteStarted(routePublicId: 'route1', driverUserId: 42);
        $this->listener->onRouteStarted($event);
    }

    #[Test]
    public function onRouteCompletedCreatesCompletedEvent(): void
    {
        $route = $this->createRoute();
        $this->stubRoute($route);
        $this->userRepo->method('find')->willReturn(null);

        $this->em->expects(self::once())
            ->method('persist')
            ->with(self::callback(fn (RouteEvent $e): bool => $e->getEventType() === RouteEventType::COMPLETED && $e->getActorType() === 'driver'));

        $event = new RouteCompleted(routePublicId: 'route1', driverUserId: 1);
        $this->listener->onRouteCompleted($event);
    }

    #[Test]
    public function onStopDeliveredCreatesStopDeliveredEvent(): void
    {
        $route = $this->createRoute();
        $this->stubRoute($route);
        $this->userRepo->method('find')->willReturn(null);

        $this->em->expects(self::once())
            ->method('persist')
            ->with(self::callback(function (RouteEvent $e): bool {
                return $e->getEventType() === RouteEventType::STOP_DELIVERED
                    && $e->getActorType() === 'driver'
                    && $e->getPayload()['stop_public_id'] === 'stop1'
                    && $e->getPayload()['pod_public_id'] === 'pod1';
            }));

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
    public function onStopExceptionReportedCreatesStopExceptionEvent(): void
    {
        $route = $this->createRoute();
        $this->stubRoute($route);
        $this->userRepo->method('find')->willReturn(null);

        $this->em->expects(self::once())
            ->method('persist')
            ->with(self::callback(function (RouteEvent $e): bool {
                return $e->getEventType() === RouteEventType::STOP_EXCEPTION
                    && $e->getPayload()['exception_code'] === 'ABSENT';
            }));

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
    public function onRouteCancelledCreatesCancelledEvent(): void
    {
        $route = $this->createRoute();
        $this->stubRoute($route);

        $admin = $this->createMock(User::class);
        $this->userRepo->method('find')->with(5)->willReturn($admin);

        $this->em->expects(self::once())
            ->method('persist')
            ->with(self::callback(function (RouteEvent $e) use ($admin): bool {
                return $e->getEventType() === RouteEventType::CANCELLED
                    && $e->getActorType() === 'admin'
                    && $e->getActorUser() === $admin
                    && $e->getPayload()['reason'] === 'No vehicles available';
            }));

        $event = new RouteCancelled(
            routePublicId: 'route1',
            cancelledByUserId: 5,
            reason: 'No vehicles available',
        );
        $this->listener->onRouteCancelled($event);
    }

    #[Test]
    public function onRouteAssignedCreatesAssignedEvent(): void
    {
        $route = $this->createRoute();
        $this->stubRoute($route);

        $admin = $this->createMock(User::class);
        $this->userRepo->method('find')->with(5)->willReturn($admin);

        $this->em->expects(self::once())
            ->method('persist')
            ->with(self::callback(function (RouteEvent $e): bool {
                return $e->getEventType() === RouteEventType::ASSIGNED
                    && $e->getActorType() === 'admin'
                    && $e->getPayload()['vehicle_public_id'] === 'veh1'
                    && $e->getPayload()['driver_user_id'] === 10;
            }));

        $event = new RouteAssigned(
            routePublicId: 'route1',
            vehiclePublicId: 'veh1',
            driverUserId: 10,
            assignedByUserId: 5,
        );
        $this->listener->onRouteAssigned($event);
    }

    #[Test]
    public function missingRouteIsNoOp(): void
    {
        $this->routeRepo->method('findOneByPublicId')->willReturn(null);

        $this->em->expects(self::never())->method('persist');
        $this->em->expects(self::never())->method('flush');

        $event = new RouteStarted(routePublicId: 'nonexistent', driverUserId: 1);
        $this->listener->onRouteStarted($event);
    }

    #[Test]
    public function snapshotMetricsAreIncluded(): void
    {
        $route = $this->createRoute();
        $this->stubRoute($route);
        $this->userRepo->method('find')->willReturn(null);

        // Return empty stops — metrics should be all zeros
        $this->em->expects(self::once())
            ->method('persist')
            ->with(self::callback(function (RouteEvent $e): bool {
                $metrics = $e->getSnapshotMetrics();

                return $metrics !== null
                    && $metrics['total_stops'] === 0
                    && $metrics['delivered'] === 0
                    && $metrics['exceptions'] === 0
                    && $metrics['pending'] === 0;
            }));

        $event = new RouteStarted(routePublicId: 'route1', driverUserId: 1);
        $this->listener->onRouteStarted($event);
    }
}
