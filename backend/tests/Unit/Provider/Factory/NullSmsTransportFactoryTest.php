<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider\Factory;

use App\Notification\Transport\NullSmsTransport;
use App\Provider\Factory\NullSmsTransportFactory;
use App\Provider\ServiceType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullSmsTransportFactory::class)]
final class NullSmsTransportFactoryTest extends TestCase
{
    #[Test]
    public function create_returns_null_sms_transport(): void
    {
        $factory = new NullSmsTransportFactory();

        $transport = $factory->create([]);

        self::assertInstanceOf(NullSmsTransport::class, $transport);
    }

    #[Test]
    public function get_provider_type_returns_null(): void
    {
        $factory = new NullSmsTransportFactory();

        self::assertSame('null', $factory->getProviderType());
    }

    #[Test]
    public function get_service_type_returns_sms_notifier(): void
    {
        $factory = new NullSmsTransportFactory();

        self::assertSame(ServiceType::SmsNotifier, $factory->getServiceType());
    }
}
