<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider\RouteOptimizer;

use App\Entity\Customer;
use App\Provider\ProviderResolverInterface;
use App\Provider\RouteOptimizer\TenantAwareRouteOptimizer;
use App\Provider\ServiceType;
use App\Provider\TenantContext;
use App\RouteOptimization\OptimizationResult;
use App\RouteOptimization\RouteOptimizerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TenantAwareRouteOptimizer::class)]
final class TenantAwareRouteOptimizerTest extends TestCase
{
    #[Test]
    public function implementsRouteOptimizerInterface(): void
    {
        $resolver = $this->createMock(ProviderResolverInterface::class);
        $tenantContext = $this->createMock(TenantContext::class);

        $proxy = new TenantAwareRouteOptimizer($resolver, $tenantContext);

        self::assertInstanceOf(RouteOptimizerInterface::class, $proxy);
    }

    #[Test]
    public function optimizeDelegatesToResolvedProvider(): void
    {
        $customer = $this->createMock(Customer::class);
        $expectedResult = new OptimizationResult(routes: [], unassignedJobIds: []);

        $innerOptimizer = $this->createMock(RouteOptimizerInterface::class);
        $innerOptimizer->expects(self::once())
            ->method('optimize')
            ->with([], [])
            ->willReturn($expectedResult);

        $resolver = $this->createMock(ProviderResolverInterface::class);
        $resolver->expects(self::once())
            ->method('resolve')
            ->with(ServiceType::RouteOptimizer, $customer)
            ->willReturn($innerOptimizer);

        $tenantContext = $this->createMock(TenantContext::class);
        $tenantContext->method('getCustomer')->willReturn($customer);

        $proxy = new TenantAwareRouteOptimizer($resolver, $tenantContext);
        $result = $proxy->optimize([], []);

        self::assertSame($expectedResult, $result);
    }

    #[Test]
    public function optimizeUsesNullCustomerWhenNoTenantInContext(): void
    {
        $expectedResult = new OptimizationResult(routes: [], unassignedJobIds: []);

        $innerOptimizer = $this->createMock(RouteOptimizerInterface::class);
        $innerOptimizer->expects(self::once())
            ->method('optimize')
            ->willReturn($expectedResult);

        $resolver = $this->createMock(ProviderResolverInterface::class);
        $resolver->expects(self::once())
            ->method('resolve')
            ->with(ServiceType::RouteOptimizer, null)
            ->willReturn($innerOptimizer);

        $tenantContext = $this->createMock(TenantContext::class);
        $tenantContext->method('getCustomer')->willReturn(null);

        $proxy = new TenantAwareRouteOptimizer($resolver, $tenantContext);
        $result = $proxy->optimize([], []);

        self::assertSame($expectedResult, $result);
    }
}
