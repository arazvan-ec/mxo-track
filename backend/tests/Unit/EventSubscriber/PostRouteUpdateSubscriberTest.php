<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Domain\Event\RouteCompleted;
use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteStop;
use App\Domain\Route\Repository\RouteRepositoryInterface;
use App\Domain\Route\Repository\RouteStopRepositoryInterface;
use App\Entity\OptimizationStrategyComparison;
use App\Enum\RouteStopStatus;
use App\EventSubscriber\PostRouteUpdateSubscriber;
use App\Repository\OptimizationStrategyComparisonRepository;
use App\Service\AddressRiskService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(PostRouteUpdateSubscriber::class)]
final class PostRouteUpdateSubscriberTest extends TestCase
{
    private RouteRepositoryInterface $routeRepo;
    private RouteStopRepositoryInterface $stopRepo;
    private AddressRiskService $addressRiskService;
    private EntityManagerInterface $em;
    private OptimizationStrategyComparisonRepository $comparisonRepo;
    private PostRouteUpdateSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->routeRepo = $this->createMock(RouteRepositoryInterface::class);
        $this->stopRepo = $this->createMock(RouteStopRepositoryInterface::class);
        $this->addressRiskService = $this->createMock(AddressRiskService::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->comparisonRepo = $this->createMock(OptimizationStrategyComparisonRepository::class);

        $this->subscriber = new PostRouteUpdateSubscriber(
            $this->routeRepo,
            $this->stopRepo,
            $this->addressRiskService,
            $this->em,
            $this->comparisonRepo,
            new NullLogger(),
        );
    }

    #[Test]
    public function updates_address_risk_from_route_stops_on_route_completed(): void
    {
        $route = $this->createMock(Route::class);
        $this->routeRepo->method('findOneByPublicId')->with('route-abc')->willReturn($route);

        $stop1 = $this->createRouteStop($route, RouteStopStatus::DELIVERED);
        $stop2 = $this->createRouteStop($route, RouteStopStatus::EXCEPTION);
        $stops = [$stop1, $stop2];

        $this->stopRepo->method('findByRoute')->with($route)->willReturn($stops);

        $this->addressRiskService->expects(self::once())
            ->method('updateFromRouteStops')
            ->with($stops);

        // No linked comparison
        $this->comparisonRepo->method('findOneBy')->willReturn(null);

        $event = new RouteCompleted('route-abc', 1);
        $this->subscriber->onRouteCompleted($event);
    }

    #[Test]
    public function logs_warning_and_does_not_crash_when_route_not_found(): void
    {
        $this->routeRepo->method('findOneByPublicId')->with('missing-route')->willReturn(null);

        $this->stopRepo->expects(self::never())->method('findByRoute');
        $this->addressRiskService->expects(self::never())->method('updateFromRouteStops');

        $event = new RouteCompleted('missing-route', 1);
        // Should not throw
        $this->subscriber->onRouteCompleted($event);
    }

    #[Test]
    public function records_outcome_on_linked_optimization_strategy_comparison(): void
    {
        $route = $this->createMock(Route::class);
        $this->routeRepo->method('findOneByPublicId')->with('route-xyz')->willReturn($route);

        // 3 DELIVERED, 1 EXCEPTION, 1 SKIPPED, 1 PENDING — total stops = 6
        $stops = [
            $this->createRouteStop($route, RouteStopStatus::DELIVERED),
            $this->createRouteStop($route, RouteStopStatus::DELIVERED),
            $this->createRouteStop($route, RouteStopStatus::DELIVERED),
            $this->createRouteStop($route, RouteStopStatus::EXCEPTION),
            $this->createRouteStop($route, RouteStopStatus::SKIPPED),
            $this->createRouteStop($route, RouteStopStatus::PENDING),
        ];
        $this->stopRepo->method('findByRoute')->with($route)->willReturn($stops);
        $this->addressRiskService->expects(self::once())->method('updateFromRouteStops');

        $comparison = $this->createMock(OptimizationStrategyComparison::class);
        $this->comparisonRepo->method('findOneBy')
            ->with(['resultRoute' => $route])
            ->willReturn($comparison);

        $comparison->expects(self::once())
            ->method('recordOutcome')
            ->with([
                'delivery_count' => 3,
                'exception_count' => 1,
                'skipped_count' => 1,
                'total_stops' => 6,
            ]);

        $this->em->expects(self::once())->method('flush');

        $event = new RouteCompleted('route-xyz', 1);
        $this->subscriber->onRouteCompleted($event);
    }

    #[Test]
    public function does_not_error_when_no_linked_comparison_exists(): void
    {
        $route = $this->createMock(Route::class);
        $this->routeRepo->method('findOneByPublicId')->with('route-no-comp')->willReturn($route);

        $stops = [$this->createRouteStop($route, RouteStopStatus::DELIVERED)];
        $this->stopRepo->method('findByRoute')->with($route)->willReturn($stops);
        $this->addressRiskService->expects(self::once())->method('updateFromRouteStops');

        $this->comparisonRepo->method('findOneBy')
            ->with(['resultRoute' => $route])
            ->willReturn(null);

        // flush should NOT be called when there's no comparison to update
        $this->em->expects(self::never())->method('flush');

        $event = new RouteCompleted('route-no-comp', 1);
        // Should not throw
        $this->subscriber->onRouteCompleted($event);
    }

    #[Test]
    public function subscribes_to_route_completed_event(): void
    {
        $events = PostRouteUpdateSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(RouteCompleted::class, $events);
        self::assertSame('onRouteCompleted', $events[RouteCompleted::class]);
    }

    private function createRouteStop(Route $route, RouteStopStatus $status): RouteStop
    {
        $stop = $this->createMock(RouteStop::class);
        $stop->method('getStatus')->willReturn($status);
        $stop->method('getRoute')->willReturn($route);
        return $stop;
    }
}
