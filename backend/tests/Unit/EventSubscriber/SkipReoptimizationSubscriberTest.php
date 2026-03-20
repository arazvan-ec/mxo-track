<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Domain\Event\RouteReoptimized;
use App\Domain\Event\StopSkipped;
use App\Domain\Route\Model\Route;
use App\Entity\Vehicle;
use App\Entity\VehicleLastPosition;
use App\Enum\RouteStatus;
use App\EventSubscriber\SkipReoptimizationSubscriber;
use App\Repository\RouteRepository;
use App\Service\RouteOptimizationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[CoversClass(SkipReoptimizationSubscriber::class)]
final class SkipReoptimizationSubscriberTest extends TestCase
{
    private RouteRepository $routeRepo;
    private RouteOptimizationService $optimizer;
    private EntityManagerInterface $em;
    private EventDispatcherInterface $dispatcher;
    private SkipReoptimizationSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->routeRepo = $this->createMock(RouteRepository::class);
        $this->optimizer = $this->createMock(RouteOptimizationService::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->subscriber = new SkipReoptimizationSubscriber(
            $this->routeRepo,
            $this->optimizer,
            $this->em,
            new NullLogger(),
            $this->dispatcher,
        );
    }

    #[Test]
    public function reoptimizes_on_skip_when_auto_enabled(): void
    {
        $route = $this->createActiveAutoReoptimizeRoute();
        $this->routeRepo->method('findOneByPublicId')->willReturn($route);

        $this->setupVehiclePosition($route);

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
            ->with(self::callback(fn ($e) => $e instanceof RouteReoptimized && $e->trigger === 'skip'));

        $event = new StopSkipped('stop1', 'route1', 1, 'test reason');
        $this->subscriber->onStopSkipped($event);
    }

    #[Test]
    public function does_not_reoptimize_when_auto_disabled(): void
    {
        $route = $this->createMock(Route::class);
        $route->method('isAutoReoptimize')->willReturn(false);
        $route->method('getStatus')->willReturn(RouteStatus::ACTIVE);
        $this->routeRepo->method('findOneByPublicId')->willReturn($route);

        $this->optimizer->expects(self::never())->method('reoptimizePendingStops');

        $event = new StopSkipped('stop1', 'route1', 1);
        $this->subscriber->onStopSkipped($event);
    }

    #[Test]
    public function does_not_reoptimize_when_route_not_active(): void
    {
        $route = $this->createMock(Route::class);
        $route->method('isAutoReoptimize')->willReturn(true);
        $route->method('getStatus')->willReturn(RouteStatus::DONE);
        $this->routeRepo->method('findOneByPublicId')->willReturn($route);

        $this->optimizer->expects(self::never())->method('reoptimizePendingStops');

        $event = new StopSkipped('stop1', 'route1', 1);
        $this->subscriber->onStopSkipped($event);
    }

    private function createActiveAutoReoptimizeRoute(): Route
    {
        $route = $this->createMock(Route::class);
        $route->method('isAutoReoptimize')->willReturn(true);
        $route->method('getStatus')->willReturn(RouteStatus::ACTIVE);
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
