<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification;

use App\Entity\Customer;
use App\Entity\NotificationPreference;
use App\Entity\Shipment;
use App\Enum\NotificationChannel;
use App\Enum\NotificationTriggerType;
use App\Notification\NotificationCommand;
use App\Notification\NotificationResolver;
use App\Repository\NotificationPreferenceRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(NotificationResolver::class)]
final class NotificationResolverTest extends TestCase
{
    private NotificationPreferenceRepository&MockObject $prefRepo;
    private NotificationResolver $resolver;

    protected function setUp(): void
    {
        $this->prefRepo = $this->createMock(NotificationPreferenceRepository::class);
        $this->resolver = new NotificationResolver($this->prefRepo, 'https://track.example.com');
    }

    #[Test]
    public function it_falls_back_to_sms_default_when_no_preferences(): void
    {
        $customer = new Customer('Test Corp');
        $shipment = new Shipment('REF-001', $customer);
        $shipment->setRecipientName('Juan');
        $shipment->setRecipientPhone('+34600000001');

        $this->prefRepo->method('findEnabledByCustomerAndTrigger')->willReturn([]);

        $commands = $this->resolver->resolve($shipment, NotificationTriggerType::Reminder);

        self::assertCount(1, $commands);
        self::assertInstanceOf(NotificationCommand::class, $commands[0]);
        self::assertSame(NotificationChannel::Sms, $commands[0]->channel);
        self::assertStringContainsString('Juan', $commands[0]->message);
    }

    #[Test]
    public function it_resolves_from_customer_preferences(): void
    {
        $customer = $this->createCustomerWithId('1');
        $shipment = new Shipment('REF-001', $customer);
        $shipment->setRecipientName('Maria');
        $shipment->setRecipientPhone('+34600000002');

        $pref1 = new NotificationPreference($customer, NotificationTriggerType::Reminder, NotificationChannel::Sms);
        $pref2 = new NotificationPreference($customer, NotificationTriggerType::Reminder, NotificationChannel::WhatsApp);

        $this->prefRepo->method('findEnabledByCustomerAndTrigger')->willReturn([$pref1, $pref2]);

        $commands = $this->resolver->resolve($shipment, NotificationTriggerType::Reminder);

        self::assertCount(2, $commands);
        self::assertSame(NotificationChannel::Sms, $commands[0]->channel);
        self::assertSame(NotificationChannel::WhatsApp, $commands[1]->channel);
    }

    #[Test]
    public function it_uses_custom_template_from_preference(): void
    {
        $customer = $this->createCustomerWithId('1');
        $shipment = new Shipment('REF-001', $customer);
        $shipment->setRecipientName('Pedro');

        $pref = new NotificationPreference(
            $customer,
            NotificationTriggerType::Delivered,
            NotificationChannel::Sms,
            messageTemplate: 'Entregado a {recipient_name}!',
        );

        $this->prefRepo->method('findEnabledByCustomerAndTrigger')->willReturn([$pref]);

        $commands = $this->resolver->resolve($shipment, NotificationTriggerType::Delivered);

        self::assertCount(1, $commands);
        self::assertSame('Entregado a Pedro!', $commands[0]->message);
    }

    #[Test]
    public function it_renders_tracking_url_placeholder(): void
    {
        $customer = new Customer('Test Corp');
        $shipment = new Shipment('REF-001', $customer);
        $shipment->setRecipientName('Ana');

        $this->prefRepo->method('findEnabledByCustomerAndTrigger')->willReturn([]);

        $commands = $this->resolver->resolve($shipment, NotificationTriggerType::Delivered);

        self::assertStringContainsString('https://track.example.com/track/', $commands[0]->message);
    }

    private function createCustomerWithId(string $id): Customer
    {
        $customer = new Customer('Test Corp');
        $r = new \ReflectionProperty($customer, 'id');
        $r->setValue($customer, $id);

        return $customer;
    }
}
