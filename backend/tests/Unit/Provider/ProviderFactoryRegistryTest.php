<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider;

use App\Entity\Customer;
use App\Entity\CustomerIntegration;
use App\Provider\ProviderFactoryInterface;
use App\Provider\ProviderFactoryRegistry;
use App\Provider\ServiceType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProviderFactoryRegistry::class)]
final class ProviderFactoryRegistryTest extends TestCase
{
    #[Test]
    public function create_returns_provider_from_factory(): void
    {
        $expectedProvider = new \stdClass();
        $factory = $this->createFactory('osrm', ServiceType::RoutingEngine, $expectedProvider);

        $registry = new ProviderFactoryRegistry([$factory], []);

        $customer = new Customer('Test');
        $integration = new CustomerIntegration($customer, ServiceType::RoutingEngine, 'osrm');

        $result = $registry->create($integration);
        self::assertSame($expectedProvider, $result);
    }

    #[Test]
    public function create_throws_on_unknown_provider_type(): void
    {
        $registry = new ProviderFactoryRegistry([], []);

        $customer = new Customer('Test');
        $integration = new CustomerIntegration($customer, ServiceType::RoutingEngine, 'unknown');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown provider: unknown');
        $registry->create($integration);
    }

    #[Test]
    public function create_default_uses_configured_defaults(): void
    {
        $expectedProvider = new \stdClass();
        $factory = $this->createFactory('vroom', ServiceType::RouteOptimizer, $expectedProvider);

        $registry = new ProviderFactoryRegistry([$factory], [
            'route_optimizer' => 'vroom',
        ]);

        $result = $registry->createDefault(ServiceType::RouteOptimizer);
        self::assertSame($expectedProvider, $result);
    }

    #[Test]
    public function create_default_throws_when_no_default_configured(): void
    {
        $registry = new ProviderFactoryRegistry([], []);

        $this->expectException(\RuntimeException::class);
        $registry->createDefault(ServiceType::RoutingEngine);
    }

    #[Test]
    public function get_available_providers_returns_grouped_by_service(): void
    {
        $factory1 = $this->createFactory('vroom', ServiceType::RouteOptimizer, new \stdClass());
        $factory2 = $this->createFactory('osrm', ServiceType::RoutingEngine, new \stdClass());
        $factory3 = $this->createFactory('haversine', ServiceType::RoutingEngine, new \stdClass());

        $registry = new ProviderFactoryRegistry([$factory1, $factory2, $factory3], []);

        $available = $registry->getAvailableProviders();
        self::assertArrayHasKey('route_optimizer', $available);
        self::assertArrayHasKey('routing_engine', $available);
        self::assertSame(['vroom'], $available['route_optimizer']);
        self::assertEqualsCanonicalizing(['osrm', 'haversine'], $available['routing_engine']);
    }

    private function createFactory(string $providerType, ServiceType $serviceType, object $result): ProviderFactoryInterface
    {
        return new class ($providerType, $serviceType, $result) implements ProviderFactoryInterface {
            public function __construct(
                private readonly string $pt,
                private readonly ServiceType $st,
                private readonly object $result,
            ) {
            }

            public function create(array $config): object
            {
                return $this->result;
            }

            public function getProviderType(): string
            {
                return $this->pt;
            }

            public function getServiceType(): ServiceType
            {
                return $this->st;
            }
        };
    }
}
