<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider;

use App\Entity\Customer;
use App\Provider\CachedProviderResolver;
use App\Provider\FallbackChain;
use App\Provider\ProviderResolverInterface;
use App\Provider\ServiceType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CachedProviderResolver::class)]
final class CachedProviderResolverTest extends TestCase
{
    #[Test]
    public function it_delegates_to_inner_on_first_call(): void
    {
        $customer = new Customer('Test');
        $expected = new \stdClass();

        $inner = $this->createMock(ProviderResolverInterface::class);
        $inner->expects(self::once())
            ->method('resolve')
            ->with(ServiceType::RoutingEngine, $customer)
            ->willReturn($expected);

        $cached = new CachedProviderResolver($inner);
        $result = $cached->resolve(ServiceType::RoutingEngine, $customer);

        self::assertSame($expected, $result);
    }

    #[Test]
    public function it_returns_cached_on_second_call(): void
    {
        $customer = new Customer('Test');
        $expected = new \stdClass();

        $inner = $this->createMock(ProviderResolverInterface::class);
        $inner->expects(self::once()) // Only called once despite two resolve() calls
            ->method('resolve')
            ->willReturn($expected);

        $cached = new CachedProviderResolver($inner);
        $cached->resolve(ServiceType::RoutingEngine, $customer);
        $result2 = $cached->resolve(ServiceType::RoutingEngine, $customer);

        self::assertSame($expected, $result2);
    }

    #[Test]
    public function it_caches_separately_by_service_type(): void
    {
        $customer = new Customer('Test');
        $routingProvider = new \stdClass();
        $optimizerProvider = new \stdClass();

        $inner = $this->createMock(ProviderResolverInterface::class);
        $inner->expects(self::exactly(2))
            ->method('resolve')
            ->willReturnCallback(fn (ServiceType $s) => match ($s) {
                ServiceType::RoutingEngine => $routingProvider,
                ServiceType::RouteOptimizer => $optimizerProvider,
                default => new \stdClass(),
            });

        $cached = new CachedProviderResolver($inner);
        self::assertSame($routingProvider, $cached->resolve(ServiceType::RoutingEngine, $customer));
        self::assertSame($optimizerProvider, $cached->resolve(ServiceType::RouteOptimizer, $customer));
    }

    #[Test]
    public function it_delegates_resolve_with_fallback(): void
    {
        $chain = new FallbackChain([new \stdClass()]);

        $inner = $this->createMock(ProviderResolverInterface::class);
        $inner->method('resolveWithFallback')->willReturn($chain);

        $cached = new CachedProviderResolver($inner);
        self::assertSame($chain, $cached->resolveWithFallback(ServiceType::RoutingEngine, null));
    }
}
