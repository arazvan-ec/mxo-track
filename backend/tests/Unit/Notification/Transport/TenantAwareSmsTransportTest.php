<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Transport;

use App\Entity\Customer;
use App\Notification\Transport\NullSmsTransport;
use App\Notification\Transport\TenantAwareSmsTransport;
use App\Provider\ProviderResolverInterface;
use App\Provider\ServiceType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\TransportInterface;

#[CoversClass(TenantAwareSmsTransport::class)]
final class TenantAwareSmsTransportTest extends TestCase
{
    #[Test]
    public function send_without_customer_uses_default_transport(): void
    {
        $defaultTransport = $this->createMock(TransportInterface::class);
        $sentMessage = new SentMessage(new SmsMessage('+34600000000', 'test'), 'null');
        $defaultTransport->method('send')->willReturn($sentMessage);

        $resolver = $this->createMock(ProviderResolverInterface::class);
        $resolver->expects(self::never())->method('resolve');

        $transport = new TenantAwareSmsTransport($resolver, $defaultTransport);

        $result = $transport->send(new SmsMessage('+34600000000', 'Hello'));

        self::assertSame($sentMessage, $result);
    }

    #[Test]
    public function send_with_customer_resolves_via_provider_resolver(): void
    {
        $customer = $this->createMock(Customer::class);
        $sms = new SmsMessage('+34600000000', 'Hello');

        $tenantTransport = $this->createMock(TransportInterface::class);
        $sentMessage = new SentMessage($sms, 'twilio');
        $tenantTransport->method('send')->willReturn($sentMessage);

        $resolver = $this->createMock(ProviderResolverInterface::class);
        $resolver->expects(self::once())
            ->method('resolve')
            ->with(ServiceType::SmsNotifier, $customer)
            ->willReturn($tenantTransport);

        $defaultTransport = $this->createMock(TransportInterface::class);
        $defaultTransport->expects(self::never())->method('send');

        $transport = new TenantAwareSmsTransport($resolver, $defaultTransport);
        $transport->setCustomer($customer);

        $result = $transport->send($sms);

        self::assertSame($sentMessage, $result);
    }

    #[Test]
    public function send_with_customer_falls_back_when_resolver_returns_non_transport(): void
    {
        $customer = $this->createMock(Customer::class);
        $sms = new SmsMessage('+34600000000', 'Hello');

        $resolver = $this->createMock(ProviderResolverInterface::class);
        $resolver->method('resolve')->willReturn(new \stdClass());

        $defaultTransport = $this->createMock(TransportInterface::class);
        $sentMessage = new SentMessage($sms, 'null');
        $defaultTransport->method('send')->willReturn($sentMessage);

        $transport = new TenantAwareSmsTransport($resolver, $defaultTransport);
        $transport->setCustomer($customer);

        $result = $transport->send($sms);

        self::assertSame($sentMessage, $result);
    }

    #[Test]
    public function set_customer_null_resets_to_default(): void
    {
        $customer = $this->createMock(Customer::class);
        $sms = new SmsMessage('+34600000000', 'Hello');

        $resolver = $this->createMock(ProviderResolverInterface::class);
        $resolver->expects(self::never())->method('resolve');

        $defaultTransport = $this->createMock(TransportInterface::class);
        $sentMessage = new SentMessage($sms, 'null');
        $defaultTransport->method('send')->willReturn($sentMessage);

        $transport = new TenantAwareSmsTransport($resolver, $defaultTransport);
        $transport->setCustomer($customer);
        $transport->setCustomer(null);

        $result = $transport->send($sms);

        self::assertSame($sentMessage, $result);
    }

    #[Test]
    public function supports_sms_message_only(): void
    {
        $resolver = $this->createMock(ProviderResolverInterface::class);
        $defaultTransport = new NullSmsTransport();

        $transport = new TenantAwareSmsTransport($resolver, $defaultTransport);

        self::assertTrue($transport->supports(new SmsMessage('+34600000000', 'test')));
        self::assertFalse($transport->supports(new ChatMessage('test')));
    }

    #[Test]
    public function to_string_returns_tenant_aware_sms(): void
    {
        $resolver = $this->createMock(ProviderResolverInterface::class);
        $defaultTransport = new NullSmsTransport();

        $transport = new TenantAwareSmsTransport($resolver, $defaultTransport);

        self::assertSame('tenant-aware-sms', (string) $transport);
    }
}
