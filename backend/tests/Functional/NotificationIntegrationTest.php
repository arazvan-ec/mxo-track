<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Notification\Message\SendRecipientNotificationHandler;
use App\Notification\Message\SendRecipientNotificationMessage;
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
    public function messenger_routes_notification_message_to_async(): void
    {
        $container = self::getContainer();

        // Dispatch a message to the bus — in test env it should go to the async transport
        $bus = $container->get(MessageBusInterface::class);
        $bus->dispatch(new SendRecipientNotificationMessage('999', 'approaching'));

        // Verify message was routed to the async transport
        /** @var MessengerTransportInterface $transport */
        $transport = $container->get('messenger.transport.async');
        $envelopes = $transport->get();

        self::assertCount(1, $envelopes);
        $message = $envelopes[0]->getMessage();
        self::assertInstanceOf(SendRecipientNotificationMessage::class, $message);
        self::assertSame('999', $message->routeStopId);
        self::assertSame('approaching', $message->notificationType);
    }
}
