<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider;

use App\Entity\Customer;
use App\Entity\CustomerIntegration;
use App\Provider\FallbackChain;
use App\Provider\ProviderFactoryRegistry;
use App\Provider\ProviderResolver;
use App\Provider\ProviderResolverInterface;
use App\Provider\ServiceType;
use App\Repository\CustomerIntegrationRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProviderResolver::class)]
final class ProviderResolverTest extends TestCase
{
    #[Test]
    public function it_implements_interface(): void
    {
        $repo = $this->createMock(CustomerIntegrationRepository::class);
        $registry = $this->createMock(ProviderFactoryRegistry::class);
        $resolver = new ProviderResolver($repo, $registry);
        self::assertInstanceOf(ProviderResolverInterface::class, $resolver);
    }

    #[Test]
    public function resolve_returns_provider_from_integration(): void
    {
        $customer = new Customer('Test');
        $integration = new CustomerIntegration($customer, ServiceType::RoutingEngine, 'osrm');

        $expectedProvider = new \stdClass();

        $repo = $this->createMock(CustomerIntegrationRepository::class);
        $repo->method('findActiveByCustomerAndService')
            ->with($customer, ServiceType::RoutingEngine)
            ->willReturn([$integration]);

        $registry = $this->createMock(ProviderFactoryRegistry::class);
        $registry->method('create')
            ->with($integration)
            ->willReturn($expectedProvider);

        $resolver = new ProviderResolver($repo, $registry);
        self::assertSame($expectedProvider, $resolver->resolve(ServiceType::RoutingEngine, $customer));
    }

    #[Test]
    public function resolve_falls_back_to_default_when_no_integrations(): void
    {
        $customer = new Customer('Test');
        $expectedProvider = new \stdClass();

        $repo = $this->createMock(CustomerIntegrationRepository::class);
        $repo->method('findActiveByCustomerAndService')->willReturn([]);

        $registry = $this->createMock(ProviderFactoryRegistry::class);
        $registry->method('createDefault')
            ->with(ServiceType::RouteOptimizer)
            ->willReturn($expectedProvider);

        $resolver = new ProviderResolver($repo, $registry);
        self::assertSame($expectedProvider, $resolver->resolve(ServiceType::RouteOptimizer, $customer));
    }

    #[Test]
    public function resolve_returns_default_when_customer_is_null(): void
    {
        $expectedProvider = new \stdClass();

        $repo = $this->createMock(CustomerIntegrationRepository::class);
        $repo->expects(self::never())->method('findActiveByCustomerAndService');

        $registry = $this->createMock(ProviderFactoryRegistry::class);
        $registry->method('createDefault')
            ->with(ServiceType::GpsProvider)
            ->willReturn($expectedProvider);

        $resolver = new ProviderResolver($repo, $registry);
        self::assertSame($expectedProvider, $resolver->resolve(ServiceType::GpsProvider, null));
    }

    #[Test]
    public function resolve_with_fallback_returns_chain_with_multiple_providers(): void
    {
        $customer = new Customer('Test');
        $integration1 = new CustomerIntegration($customer, ServiceType::RoutingEngine, 'google_directions', [], true, 0);
        $integration2 = new CustomerIntegration($customer, ServiceType::RoutingEngine, 'haversine', [], true, 1);

        $provider1 = new \stdClass();
        $provider2 = new \stdClass();

        $repo = $this->createMock(CustomerIntegrationRepository::class);
        $repo->method('findActiveByCustomerAndService')->willReturn([$integration1, $integration2]);

        $registry = $this->createMock(ProviderFactoryRegistry::class);
        $registry->method('create')
            ->willReturnCallback(fn ($i) => $i === $integration1 ? $provider1 : $provider2);

        $resolver = new ProviderResolver($repo, $registry);
        $chain = $resolver->resolveWithFallback(ServiceType::RoutingEngine, $customer);

        self::assertInstanceOf(FallbackChain::class, $chain);
    }

    #[Test]
    public function resolve_with_fallback_uses_default_when_no_integrations(): void
    {
        $defaultProvider = new \stdClass();

        $repo = $this->createMock(CustomerIntegrationRepository::class);
        $repo->method('findActiveByCustomerAndService')->willReturn([]);

        $registry = $this->createMock(ProviderFactoryRegistry::class);
        $registry->method('createDefault')->willReturn($defaultProvider);

        $resolver = new ProviderResolver($repo, $registry);
        $chain = $resolver->resolveWithFallback(ServiceType::RoutingEngine, new Customer('Test'));

        self::assertInstanceOf(FallbackChain::class, $chain);
    }
}
