<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider\Gps;

use App\Provider\Gps\GpsProviderType;
use App\Provider\Gps\WebhookGpsFactory;
use App\Provider\Gps\WebhookGpsProvider;
use App\Provider\ServiceType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebhookGpsFactory::class)]
final class WebhookGpsFactoryTest extends TestCase
{
    #[Test]
    public function createReturnsWebhookGpsProvider(): void
    {
        $factory = new WebhookGpsFactory();

        $result = $factory->create([]);

        self::assertInstanceOf(WebhookGpsProvider::class, $result);
    }

    #[Test]
    public function getProviderTypeReturnsWebhook(): void
    {
        $factory = new WebhookGpsFactory();

        self::assertSame(GpsProviderType::Webhook->value, $factory->getProviderType());
    }

    #[Test]
    public function getServiceTypeReturnsGpsProvider(): void
    {
        $factory = new WebhookGpsFactory();

        self::assertSame(ServiceType::GpsProvider, $factory->getServiceType());
    }
}
