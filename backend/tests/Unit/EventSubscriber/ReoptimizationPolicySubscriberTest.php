<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Domain\Event\StopExceptionReported;
use App\Domain\Event\StopSkipped;
use App\Domain\Event\StopDelivered;
use App\Domain\Route\Model\Route;
use App\Entity\Customer;
use App\Entity\ReoptimizationPolicy;
use App\Entity\VehicleLastPosition;
use App\Enum\RouteStatus;
use App\EventSubscriber\ExceptionReoptimizationSubscriber;
use App\EventSubscriber\SkipReoptimizationSubscriber;
use App\EventSubscriber\DelayReoptimizationSubscriber;
use App\Repository\ReoptimizationPolicyRepository;
use App\Repository\RouteRepository;
use App\Service\RouteOptimizationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReoptimizationPolicySubscriberTest extends TestCase
{
    private function createRoute(bool $autoReoptimize = false, ?Customer $customer = null): Route
    {
        $c = $customer ?? $this->createMock(Customer::class);
        $route = new Route('Test Route', $c);
        $route->setAutoReoptimize($autoReoptimize);

        // Use reflection to set status to ACTIVE
        $ref = new \ReflectionProperty(Route::class, 'status');
        $ref->setValue($route, RouteStatus::ACTIVE);

        return $route;
    }

    private function createPolicy(array $triggers, bool $enabled = true, int $delayThreshold = 30): ReoptimizationPolicy
    {
        return new ReoptimizationPolicy(
            customer: $this->createMock(Customer::class),
            triggers: $triggers,
            delayThresholdMinutes: $delayThreshold,
            enabled: $enabled,
        );
    }

    // ── ExceptionReoptimizationSubscriber ──

    #[Test]
    public function exception_subscriber_reoptimizes_when_policy_allows(): void
    {
        $customer = $this->createMock(Customer::class);
        $route = $this->createRoute(false, $customer);
        $policy = $this->createPolicy(['on_exception']);

        $routeRepo = $this->createMock(RouteRepository::class);
        $routeRepo->method('findOneByPublicId')->willReturn($route);

        $policyRepo = $this->createMock(ReoptimizationPolicyRepository::class);
        $policyRepo->method('findOneBy')->willReturn($policy);

        $optimizer = $this->createMock(RouteOptimizationService::class);
        $optimizer->expects(self::once())
            ->method('reoptimizePendingStops')
            ->willReturn(['optimized' => [], 'distanceBefore' => 10, 'distanceAfter' => 8, 'durationMinutes' => 30]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($this->createMock(\Doctrine\ORM\EntityRepository::class));

        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $subscriber = new ExceptionReoptimizationSubscriber(
            $routeRepo, $optimizer, $em, new NullLogger(), $dispatcher, $policyRepo,
        );

        $subscriber->onStopExceptionReported(new StopExceptionReported(
            routePublicId: 'test-123',
            stopPublicId: 'stop-1',
            exceptionCode: 'ABSENT',
        ));
    }

    #[Test]
    public function exception_subscriber_skips_when_policy_disallows(): void
    {
        $customer = $this->createMock(Customer::class);
        $route = $this->createRoute(false, $customer);
        $policy = $this->createPolicy(['on_skip']); // No on_exception

        $routeRepo = $this->createMock(RouteRepository::class);
        $routeRepo->method('findOneByPublicId')->willReturn($route);

        $policyRepo = $this->createMock(ReoptimizationPolicyRepository::class);
        $policyRepo->method('findOneBy')->willReturn($policy);

        $optimizer = $this->createMock(RouteOptimizationService::class);
        $optimizer->expects(self::never())->method('reoptimizePendingStops');

        $em = $this->createMock(EntityManagerInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $subscriber = new ExceptionReoptimizationSubscriber(
            $routeRepo, $optimizer, $em, new NullLogger(), $dispatcher, $policyRepo,
        );

        $subscriber->onStopExceptionReported(new StopExceptionReported(
            routePublicId: 'test-123',
            stopPublicId: 'stop-1',
            exceptionCode: 'ABSENT',
        ));
    }

    #[Test]
    public function exception_subscriber_falls_back_to_boolean_when_no_policy(): void
    {
        $route = $this->createRoute(true); // autoReoptimize=true, no policy

        $routeRepo = $this->createMock(RouteRepository::class);
        $routeRepo->method('findOneByPublicId')->willReturn($route);

        $policyRepo = $this->createMock(ReoptimizationPolicyRepository::class);
        $policyRepo->method('findOneBy')->willReturn(null); // No policy

        $optimizer = $this->createMock(RouteOptimizationService::class);
        $optimizer->expects(self::once())->method('reoptimizePendingStops')
            ->willReturn(['optimized' => [], 'distanceBefore' => 10, 'distanceAfter' => 8, 'durationMinutes' => 30]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($this->createMock(\Doctrine\ORM\EntityRepository::class));

        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $subscriber = new ExceptionReoptimizationSubscriber(
            $routeRepo, $optimizer, $em, new NullLogger(), $dispatcher, $policyRepo,
        );

        $subscriber->onStopExceptionReported(new StopExceptionReported(
            routePublicId: 'test-123',
            stopPublicId: 'stop-1',
            exceptionCode: 'ABSENT',
        ));
    }

    #[Test]
    public function exception_subscriber_skips_when_no_policy_and_boolean_false(): void
    {
        $route = $this->createRoute(false); // autoReoptimize=false, no policy

        $routeRepo = $this->createMock(RouteRepository::class);
        $routeRepo->method('findOneByPublicId')->willReturn($route);

        $policyRepo = $this->createMock(ReoptimizationPolicyRepository::class);
        $policyRepo->method('findOneBy')->willReturn(null);

        $optimizer = $this->createMock(RouteOptimizationService::class);
        $optimizer->expects(self::never())->method('reoptimizePendingStops');

        $em = $this->createMock(EntityManagerInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $subscriber = new ExceptionReoptimizationSubscriber(
            $routeRepo, $optimizer, $em, new NullLogger(), $dispatcher, $policyRepo,
        );

        $subscriber->onStopExceptionReported(new StopExceptionReported(
            routePublicId: 'test-123',
            stopPublicId: 'stop-1',
            exceptionCode: 'ABSENT',
        ));
    }
}
