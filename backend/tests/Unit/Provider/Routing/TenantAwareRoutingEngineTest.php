<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider\Routing;

use App\Entity\Customer;
use App\Provider\ProviderResolverInterface;
use App\Provider\Routing\TenantAwareRoutingEngine;
use App\Provider\ServiceType;
use App\Provider\TenantContext;
use App\Routing\Coordinate;
use App\Routing\MultiWaypointRouteResult;
use App\Routing\RouteResult;
use App\Routing\RoutingEngineInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TenantAwareRoutingEngine::class)]
final class TenantAwareRoutingEngineTest extends TestCase
{
    #[Test]
    public function implementsRoutingEngineInterface(): void
    {
        $resolver = $this->createMock(ProviderResolverInterface::class);
        $tenantContext = $this->createMock(TenantContext::class);

        $proxy = new TenantAwareRoutingEngine($resolver, $tenantContext);

        self::assertInstanceOf(RoutingEngineInterface::class, $proxy);
    }

    #[Test]
    public function routeDelegatesToResolvedProvider(): void
    {
        $customer = $this->createMock(Customer::class);
        $expectedResult = new RouteResult(distanceKm: 5.0, durationSeconds: 300.0);

        $innerEngine = $this->createMock(RoutingEngineInterface::class);
        $innerEngine->expects(self::once())
            ->method('route')
            ->with(40.0, -3.0, 41.0, -4.0)
            ->willReturn($expectedResult);

        $resolver = $this->createMock(ProviderResolverInterface::class);
        $resolver->expects(self::once())
            ->method('resolve')
            ->with(ServiceType::RoutingEngine, $customer)
            ->willReturn($innerEngine);

        $tenantContext = $this->createMock(TenantContext::class);
        $tenantContext->method('getCustomer')->willReturn($customer);

        $proxy = new TenantAwareRoutingEngine($resolver, $tenantContext);
        $result = $proxy->route(40.0, -3.0, 41.0, -4.0);

        self::assertSame($expectedResult, $result);
    }

    #[Test]
    public function routeWithWaypointsDelegatesToResolvedProvider(): void
    {
        $customer = $this->createMock(Customer::class);
        $expectedResult = new MultiWaypointRouteResult(
            totalDistanceKm: 10.0,
            totalDurationSeconds: 600.0,
            legs: [],
        );

        $waypoints = [
            new Coordinate(40.0, -3.0),
            new Coordinate(41.0, -4.0),
        ];

        $innerEngine = $this->createMock(RoutingEngineInterface::class);
        $innerEngine->expects(self::once())
            ->method('routeWithWaypoints')
            ->with($waypoints)
            ->willReturn($expectedResult);

        $resolver = $this->createMock(ProviderResolverInterface::class);
        $resolver->expects(self::once())
            ->method('resolve')
            ->with(ServiceType::RoutingEngine, $customer)
            ->willReturn($innerEngine);

        $tenantContext = $this->createMock(TenantContext::class);
        $tenantContext->method('getCustomer')->willReturn($customer);

        $proxy = new TenantAwareRoutingEngine($resolver, $tenantContext);
        $result = $proxy->routeWithWaypoints($waypoints);

        self::assertSame($expectedResult, $result);
    }

    #[Test]
    public function routeUsesNullCustomerWhenNoTenantInContext(): void
    {
        $expectedResult = new RouteResult(distanceKm: 1.0, durationSeconds: 60.0);

        $innerEngine = $this->createMock(RoutingEngineInterface::class);
        $innerEngine->expects(self::once())
            ->method('route')
            ->willReturn($expectedResult);

        $resolver = $this->createMock(ProviderResolverInterface::class);
        $resolver->expects(self::once())
            ->method('resolve')
            ->with(ServiceType::RoutingEngine, null)
            ->willReturn($innerEngine);

        $tenantContext = $this->createMock(TenantContext::class);
        $tenantContext->method('getCustomer')->willReturn(null);

        $proxy = new TenantAwareRoutingEngine($resolver, $tenantContext);
        $result = $proxy->route(40.0, -3.0, 41.0, -4.0);

        self::assertSame($expectedResult, $result);
    }
}
