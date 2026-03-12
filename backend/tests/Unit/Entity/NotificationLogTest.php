<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Customer;
use App\Entity\CustomerScopedEntityInterface;
use App\Entity\NotificationLog;
use App\Entity\Shipment;
use App\Enum\NotificationChannel;
use App\Enum\NotificationLogStatus;
use App\Enum\NotificationTriggerType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NotificationLog::class)]
final class NotificationLogTest extends TestCase
{
    #[Test]
    public function it_implements_customer_scoped_entity_interface(): void
    {
        $log = $this->createLog();
        self::assertInstanceOf(CustomerScopedEntityInterface::class, $log);
    }

    #[Test]
    public function it_stores_all_required_fields(): void
    {
        $customer = new Customer('Test Corp');
        $shipment = new Shipment('REF-001', $customer);

        $log = new NotificationLog(
            shipment: $shipment,
            customer: $customer,
            channel: NotificationChannel::Sms,
            triggerType: NotificationTriggerType::Reminder,
            recipientPhone: '+34600000001',
            messageContent: 'Hola, tu entrega llega mañana.',
            status: NotificationLogStatus::Sent,
        );

        self::assertSame($shipment, $log->getShipment());
        self::assertSame($customer, $log->getCustomer());
        self::assertSame(NotificationChannel::Sms, $log->getChannel());
        self::assertSame(NotificationTriggerType::Reminder, $log->getTriggerType());
        self::assertSame('+34600000001', $log->getRecipientPhone());
        self::assertSame('Hola, tu entrega llega mañana.', $log->getMessageContent());
        self::assertSame(NotificationLogStatus::Sent, $log->getStatus());
        self::assertInstanceOf(\DateTimeImmutable::class, $log->getCreatedAt());
    }

    #[Test]
    public function it_stores_provider_response(): void
    {
        $log = $this->createLog();
        self::assertSame([], $log->getProviderResponse());

        $response = ['sid' => 'SM123', 'status' => 'queued'];
        $log->setProviderResponse($response);
        self::assertSame($response, $log->getProviderResponse());
    }

    #[Test]
    public function it_defaults_provider_response_to_empty_array(): void
    {
        $log = $this->createLog();
        self::assertSame([], $log->getProviderResponse());
    }

    private function createLog(): NotificationLog
    {
        $customer = new Customer('Test Corp');
        $shipment = new Shipment('REF-001', $customer);

        return new NotificationLog(
            shipment: $shipment,
            customer: $customer,
            channel: NotificationChannel::Sms,
            triggerType: NotificationTriggerType::Delivered,
            recipientPhone: '+34600000001',
            messageContent: 'Test message',
            status: NotificationLogStatus::Sent,
        );
    }
}
