<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Notification\Message\SendNotificationHandler;
use App\Notification\Message\SendNotificationMessage;
use App\Notification\Message\SendRecipientNotificationHandler;
use App\Notification\Message\SendRecipientNotificationMessage;
use App\Notification\NotificationDispatcher;
use App\Notification\NotificationResolver;
use App\Notification\RecipientNotificationService;
use App\Notification\Transport\NullSmsTransport;
use App\Notification\Transport\TenantAwareSmsTransport;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\TransportInterface as MessengerTransportInterface;
use Symfony\Component\Notifier\NotifierInterface;

final class NotificationIntegrationTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    #[Test]
    public function container_compiles_with_notification_services(): void
    {
        $container = self::getContainer();

        self::assertInstanceOf(
            TenantAwareSmsTransport::class,
            $container->get(TenantAwareSmsTransport::class),
        );

        self::assertInstanceOf(
            NullSmsTransport::class,
            $container->get(NullSmsTransport::class),
        );

        self::assertInstanceOf(
            SendRecipientNotificationHandler::class,
            $container->get(SendRecipientNotificationHandler::class),
        );

        self::assertInstanceOf(
            RecipientNotificationService::class,
            $container->get(RecipientNotificationService::class),
        );

        self::assertInstanceOf(
            NotificationDispatcher::class,
            $container->get(NotificationDispatcher::class),
        );

        self::assertInstanceOf(
            NotificationResolver::class,
            $container->get(NotificationResolver::class),
        );

        self::assertInstanceOf(
            SendNotificationHandler::class,
            $container->get(SendNotificationHandler::class),
        );
    }

    #[Test]
    public function notifier_interface_is_available(): void
    {
        $container = self::getContainer();

        $notifier = $container->get(NotifierInterface::class);
        self::assertInstanceOf(NotifierInterface::class, $notifier);
    }

    #[Test]
    public function message_bus_is_available(): void
    {
        $container = self::getContainer();

        $bus = $container->get(MessageBusInterface::class);
        self::assertInstanceOf(MessageBusInterface::class, $bus);
    }

    #[Test]
    public function messenger_routes_notification_messages_to_async(): void
    {
        $container = self::getContainer();

        $bus = $container->get(MessageBusInterface::class);

        // Test old message routing
        $bus->dispatch(new SendRecipientNotificationMessage('999', 'approaching'));

        // Test new message routing
        $bus->dispatch(new SendNotificationMessage(
            shipmentId: 1,
            channel: 'sms',
            triggerType: 'delivered',
            recipientPhone: '+34600000000',
            message: 'Test message',
            timing: [],
        ));

        /** @var MessengerTransportInterface $transport */
        $transport = $container->get('messenger.transport.async');
        $envelopes = $transport->get();

        self::assertCount(2, $envelopes);
        self::assertInstanceOf(SendRecipientNotificationMessage::class, $envelopes[0]->getMessage());
        self::assertInstanceOf(SendNotificationMessage::class, $envelopes[1]->getMessage());
    }
}
