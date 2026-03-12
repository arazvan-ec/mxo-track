<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification;

use App\Entity\Customer;
use App\Entity\Shipment;
use App\Enum\NotificationChannel;
use App\Enum\NotificationTriggerType;
use App\Notification\NotificationCommand;
use App\Notification\NotificationDispatcher;
use App\Notification\NotificationResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(NotificationDispatcher::class)]
final class NotificationDispatcherTest extends TestCase
{
    private MessageBusInterface&MockObject $bus;
    private NotificationResolver&MockObject $resolver;
    private NotificationDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->resolver = $this->createMock(NotificationResolver::class);
        $this->dispatcher = new NotificationDispatcher($this->bus, $this->resolver);
    }

    #[Test]
    public function it_dispatches_messages_for_each_command(): void
    {
        $customer = new Customer('Test Corp');
        $shipment = new Shipment('REF-001', $customer);
        $shipment->setRecipientPhone('+34600000001');

        $commands = [
            new NotificationCommand($shipment, NotificationChannel::Sms, 'SMS msg', []),
            new NotificationCommand($shipment, NotificationChannel::WhatsApp, 'WA msg', []),
        ];

        $this->resolver->method('resolve')->willReturn($commands);
        $this->bus->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $this->dispatcher->dispatchForShipment($shipment, NotificationTriggerType::Reminder);
    }

    #[Test]
    public function it_does_nothing_when_no_commands(): void
    {
        $customer = new Customer('Test Corp');
        $shipment = new Shipment('REF-001', $customer);

        $this->resolver->method('resolve')->willReturn([]);
        $this->bus->expects(self::never())->method('dispatch');

        $this->dispatcher->dispatchForShipment($shipment, NotificationTriggerType::Reminder);
    }

    #[Test]
    public function it_skips_when_no_recipient_phone(): void
    {
        $customer = new Customer('Test Corp');
        $shipment = new Shipment('REF-001', $customer);
        // No phone set

        $commands = [
            new NotificationCommand($shipment, NotificationChannel::Sms, 'Test', []),
        ];

        $this->resolver->method('resolve')->willReturn($commands);
        $this->bus->expects(self::never())->method('dispatch');

        $this->dispatcher->dispatchForShipment($shipment, NotificationTriggerType::Reminder);
    }
}
