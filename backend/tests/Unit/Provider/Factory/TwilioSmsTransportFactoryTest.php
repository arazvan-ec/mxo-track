<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider\Factory;

use App\Provider\Factory\TwilioSmsTransportFactory;
use App\Provider\ServiceType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[CoversClass(TwilioSmsTransportFactory::class)]
final class TwilioSmsTransportFactoryTest extends TestCase
{
    #[Test]
    public function create_returns_transport_interface(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $factory = new TwilioSmsTransportFactory($httpClient);

        $transport = $factory->create([
            'account_sid' => 'AC_TEST_SID',
            'auth_token' => 'test_token',
            'from_number' => '+34600000000',
        ]);

        self::assertInstanceOf(TransportInterface::class, $transport);
    }

    #[Test]
    public function get_provider_type_returns_twilio(): void
    {
        $factory = new TwilioSmsTransportFactory($this->createMock(HttpClientInterface::class));

        self::assertSame('twilio', $factory->getProviderType());
    }

    #[Test]
    public function get_service_type_returns_sms_notifier(): void
    {
        $factory = new TwilioSmsTransportFactory($this->createMock(HttpClientInterface::class));

        self::assertSame(ServiceType::SmsNotifier, $factory->getServiceType());
    }
}
