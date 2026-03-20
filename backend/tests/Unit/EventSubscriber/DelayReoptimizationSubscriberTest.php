<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Domain\Event\RouteReoptimized;
use App\Domain\Event\StopDelivered;
use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteEvent;
use App\Entity\Vehicle;
use App\Entity\VehicleLastPosition;
use App\Enum\RouteEventType;
use App\Enum\RouteStatus;
use App\EventSubscriber\DelayReoptimizationSubscriber;
use App\Domain\Route\Repository\RouteEventRepositoryInterface;
use App\Repository\RouteRepository;
use App\Service\RouteOptimizationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[CoversClass(DelayReoptimizationSubscriber::class)]
final class DelayReoptimizationSubscriberTest extends TestCase
{
    private RouteRepository $routeRepo;
    private RouteOptimizationService $optimizer;
    private EntityManagerInterface $em;
    private EventDispatcherInterface $dispatcher;
    private RouteEventRepositoryInterface $eventRepo;
    private DelayReoptimizationSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->routeRepo = $this->createMock(RouteRepository::class);
        $this->optimizer = $this->createMock(RouteOptimizationService::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->eventRepo = $this->createMock(RouteEventRepositoryInterface::class);

        $this->subscriber = new DelayReoptimizationSubscriber(
            $this->routeRepo,
            $this->optimizer,
            $this->em,
            new NullLogger(),
            $this->dispatcher,
            $this->eventRepo,
            delayThresholdMinutes: 30,
            cooldownMinutes: 10,
        );
    }

    #[Test]
    public function reoptimizes_when_delay_exceeds_threshold(): void
    {
        $route = $this->createActiveAutoReoptimizeRoute(estimatedMinutes: 60);
        $this->routeRepo->method('findOneByPublicId')->willReturn($route);
        $this->setupVehiclePosition($route);

        // Route started 90+ minutes ago (60 min estimated + 30 min delay threshold)
        $this->eventRepo->method('findLastByTypeForRoute')
            ->willReturnCallback(function ($route, RouteEventType $type) {
                if ($type === RouteEventType::STARTED) {
                    $event = $this->createMock(RouteEvent::class);
                    $event->method('getCreatedAt')->willReturn(new \DateTimeImmutable('-95 minutes'));
                    return $event;
                }
                return null; // no recent REOPTIMIZED event
            });

        $this->optimizer->expects(self::once())
            ->method('reoptimizePendingStops')
            ->willReturn([
                'optimized' => [['stop' => new \stdClass(), 'newSequence' => 1]],
                'distanceBefore' => 100.0,
                'distanceAfter' => 80.0,
                'durationMinutes' => 60,
            ]);

        $this->optimizer->expects(self::once())->method('applyOptimizedOrder');

        $this->dispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(fn ($e) => $e instanceof RouteReoptimized && $e->trigger === 'delay'));

        $event = new StopDelivered('stop1', 'shipment1', 'route1', 1, 'pod1');
        $this->subscriber->onStopDelivered($event);
    }

    #[Test]
    public function does_not_reoptimize_when_delay_below_threshold(): void
    {
        $route = $this->createActiveAutoReoptimizeRoute(estimatedMinutes: 60);
        $this->routeRepo->method('findOneByPublicId')->willReturn($route);

        // Route started 70 min ago — only 10 min over, below 30 min threshold
        $this->eventRepo->method('findLastByTypeForRoute')
            ->willReturnCallback(function ($route, RouteEventType $type) {
                if ($type === RouteEventType::STARTED) {
                    $event = $this->createMock(RouteEvent::class);
                    $event->method('getCreatedAt')->willReturn(new \DateTimeImmutable('-70 minutes'));
                    return $event;
                }
                return null;
            });

        $this->optimizer->expects(self::never())->method('reoptimizePendingStops');

        $event = new StopDelivered('stop1', 'shipment1', 'route1', 1, 'pod1');
        $this->subscriber->onStopDelivered($event);
    }

    #[Test]
    public function does_not_reoptimize_when_auto_disabled(): void
    {
        $route = $this->createMock(Route::class);
        $route->method('isAutoReoptimize')->willReturn(false);
        $route->method('getStatus')->willReturn(RouteStatus::ACTIVE);
        $this->routeRepo->method('findOneByPublicId')->willReturn($route);

        $this->optimizer->expects(self::never())->method('reoptimizePendingStops');

        $event = new StopDelivered('stop1', 'shipment1', 'route1', 1, 'pod1');
        $this->subscriber->onStopDelivered($event);
    }

    #[Test]
    public function respects_cooldown_after_recent_reoptimization(): void
    {
        $route = $this->createActiveAutoReoptimizeRoute(estimatedMinutes: 60);
        $this->routeRepo->method('findOneByPublicId')->willReturn($route);

        // Route started 95 min ago (over threshold) BUT reoptimized 5 min ago (within cooldown)
        $this->eventRepo->method('findLastByTypeForRoute')
            ->willReturnCallback(function ($route, RouteEventType $type) {
                if ($type === RouteEventType::STARTED) {
                    $event = $this->createMock(RouteEvent::class);
                    $event->method('getCreatedAt')->willReturn(new \DateTimeImmutable('-95 minutes'));
                    return $event;
                }
                if ($type === RouteEventType::REOPTIMIZED) {
                    $event = $this->createMock(RouteEvent::class);
                    $event->method('getCreatedAt')->willReturn(new \DateTimeImmutable('-5 minutes'));
                    return $event;
                }
                return null;
            });

        $this->optimizer->expects(self::never())->method('reoptimizePendingStops');

        $event = new StopDelivered('stop1', 'shipment1', 'route1', 1, 'pod1');
        $this->subscriber->onStopDelivered($event);
    }

    private function createActiveAutoReoptimizeRoute(int $estimatedMinutes): Route
    {
        $route = $this->createMock(Route::class);
        $route->method('isAutoReoptimize')->willReturn(true);
        $route->method('getStatus')->willReturn(RouteStatus::ACTIVE);
        $route->method('getEstimatedDurationMinutes')->willReturn($estimatedMinutes);
        $route->method('getVehicle')->willReturn($this->createMock(Vehicle::class));
        return $route;
    }

    private function setupVehiclePosition(Route $route): void
    {
        $position = $this->createMock(VehicleLastPosition::class);
        $position->method('getLat')->willReturn(40.0);
        $position->method('getLng')->willReturn(-3.0);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($position);
        $this->em->method('getRepository')->willReturn($repo);
    }
}
