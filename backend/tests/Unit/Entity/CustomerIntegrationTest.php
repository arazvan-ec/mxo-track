<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Customer;
use App\Entity\CustomerIntegration;
use App\Entity\CustomerScopedEntityInterface;
use App\Provider\ServiceType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CustomerIntegration::class)]
final class CustomerIntegrationTest extends TestCase
{
    #[Test]
    public function it_implements_customer_scoped_entity_interface(): void
    {
        $customer = new Customer('Test Corp');
        $integration = new CustomerIntegration($customer, ServiceType::RoutingEngine, 'osrm');
        self::assertInstanceOf(CustomerScopedEntityInterface::class, $integration);
    }

    #[Test]
    public function it_stores_all_fields(): void
    {
        $customer = new Customer('Test Corp');
        $integration = new CustomerIntegration($customer, ServiceType::RouteOptimizer, 'vroom');

        self::assertSame($customer, $integration->getCustomer());
        self::assertSame(ServiceType::RouteOptimizer, $integration->getServiceType());
        self::assertSame('vroom', $integration->getProviderType());
        self::assertSame([], $integration->getConfig());
        self::assertTrue($integration->isEnabled());
        self::assertSame(0, $integration->getPriority());
    }

    #[Test]
    public function it_accepts_config_and_priority(): void
    {
        $customer = new Customer('Test Corp');
        $config = ['api_key' => 'abc123', 'region' => 'es'];
        $integration = new CustomerIntegration(
            $customer,
            ServiceType::RoutingEngine,
            'google_directions',
            $config,
            true,
            1,
        );

        self::assertSame($config, $integration->getConfig());
        self::assertSame(1, $integration->getPriority());
    }

    #[Test]
    public function it_can_be_disabled(): void
    {
        $customer = new Customer('Test Corp');
        $integration = new CustomerIntegration($customer, ServiceType::GpsProvider, 'traccar');

        $integration->setEnabled(false);
        self::assertFalse($integration->isEnabled());
    }

    #[Test]
    public function it_can_update_config(): void
    {
        $customer = new Customer('Test Corp');
        $integration = new CustomerIntegration($customer, ServiceType::RealtimePublisher, 'mercure');

        $newConfig = ['hub_url' => 'https://example.com/.well-known/mercure'];
        $integration->setConfig($newConfig);
        self::assertSame($newConfig, $integration->getConfig());
    }

    #[Test]
    public function it_initializes_public_id_on_persist(): void
    {
        $customer = new Customer('Test Corp');
        $integration = new CustomerIntegration($customer, ServiceType::RoutingEngine, 'osrm');
        $integration->initializePublicId();
        self::assertNotNull($integration->getPublicId());
    }

    #[Test]
    public function it_sets_timestamps(): void
    {
        $customer = new Customer('Test Corp');
        $integration = new CustomerIntegration($customer, ServiceType::RoutingEngine, 'osrm');
        self::assertInstanceOf(\DateTimeImmutable::class, $integration->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $integration->getUpdatedAt());
    }
}
