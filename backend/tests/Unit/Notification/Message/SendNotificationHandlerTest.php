<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Message;

use App\Entity\Customer;
use App\Entity\NotificationLog;
use App\Entity\Shipment;
use App\Enum\NotificationChannel;
use App\Enum\NotificationLogStatus;
use App\Enum\NotificationTriggerType;
use App\Notification\Gate\CustomerNotificationQuota;
use App\Notification\Gate\QuietHoursGuard;
use App\Notification\Gate\RecipientThrottle;
use App\Notification\Message\SendNotificationHandler;
use App\Notification\Message\SendNotificationMessage;
use App\Notification\Transport\TenantAwareSmsTransport;
use App\Repository\NotificationLogRepository;
use App\Repository\ShipmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;

#[CoversClass(SendNotificationHandler::class)]
final class SendNotificationHandlerTest extends TestCase
{
    private ShipmentRepository&MockObject $shipmentRepo;
    private NotificationLogRepository&MockObject $logRepo;
    private TenantAwareSmsTransport&MockObject $transport;
    private RecipientThrottle&MockObject $throttle;
    private CustomerNotificationQuota&MockObject $quota;
    private QuietHoursGuard&MockObject $quietHours;
    private EntityManagerInterface&MockObject $em;
    private MessageBusInterface&MockObject $bus;
    private SendNotificationHandler $handler;

    protected function setUp(): void
    {
        $this->shipmentRepo = $this->createMock(ShipmentRepository::class);
        $this->logRepo = $this->createMock(NotificationLogRepository::class);
        $this->transport = $this->createMock(TenantAwareSmsTransport::class);
        $this->throttle = $this->createMock(RecipientThrottle::class);
        $this->quota = $this->createMock(CustomerNotificationQuota::class);
        $this->quietHours = $this->createMock(QuietHoursGuard::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->bus = $this->createMock(MessageBusInterface::class);

        $this->handler = new SendNotificationHandler(
            $this->shipmentRepo,
            $this->logRepo,
            $this->transport,
            $this->throttle,
            $this->quota,
            $this->quietHours,
            $this->em,
            $this->bus,
        );
    }

    #[Test]
    public function it_sends_and_logs_success(): void
    {
        $customer = new Customer('Test Corp');
        $shipment = new Shipment('REF-001', $customer);

        $this->shipmentRepo->method('find')->willReturn($shipment);
        $this->logRepo->method('hasBeenSent')->willReturn(false);
        $this->quietHours->method('canSendNow')->willReturn(true);
        $this->throttle->method('canSend')->willReturn(true);
        $this->quota->method('canSend')->willReturn(true);

        $sentMessage = $this->createMock(SentMessage::class);
        $this->transport->method('send')->willReturn($sentMessage);

        $this->em->expects(self::once())->method('persist')
            ->with(self::callback(fn (NotificationLog $log) => $log->getStatus() === NotificationLogStatus::Sent));
        $this->em->expects(self::once())->method('flush');

        ($this->handler)($this->createMessage());
    }

    #[Test]
    public function it_skips_if_shipment_not_found(): void
    {
        $this->shipmentRepo->method('find')->willReturn(null);
        $this->em->expects(self::never())->method('persist');

        ($this->handler)($this->createMessage());
    }

    #[Test]
    public function it_skips_if_already_sent(): void
    {
        $customer = new Customer('Test Corp');
        $shipment = new Shipment('REF-001', $customer);

        $this->shipmentRepo->method('find')->willReturn($shipment);
        $this->logRepo->method('hasBeenSent')->willReturn(true);

        $this->transport->expects(self::never())->method('send');
        $this->em->expects(self::never())->method('persist');

        ($this->handler)($this->createMessage());
    }

    #[Test]
    public function it_logs_throttled_when_recipient_limit_exceeded(): void
    {
        $customer = new Customer('Test Corp');
        $shipment = new Shipment('REF-001', $customer);

        $this->shipmentRepo->method('find')->willReturn($shipment);
        $this->logRepo->method('hasBeenSent')->willReturn(false);
        $this->quietHours->method('canSendNow')->willReturn(true);
        $this->throttle->method('canSend')->willReturn(false);

        $this->transport->expects(self::never())->method('send');
        $this->em->expects(self::once())->method('persist')
            ->with(self::callback(fn (NotificationLog $log) => $log->getStatus() === NotificationLogStatus::Throttled));

        ($this->handler)($this->createMessage());
    }

    #[Test]
    public function it_logs_throttled_when_customer_quota_exceeded(): void
    {
        $customer = new Customer('Test Corp');
        $shipment = new Shipment('REF-001', $customer);

        $this->shipmentRepo->method('find')->willReturn($shipment);
        $this->logRepo->method('hasBeenSent')->willReturn(false);
        $this->quietHours->method('canSendNow')->willReturn(true);
        $this->throttle->method('canSend')->willReturn(true);
        $this->quota->method('canSend')->willReturn(false);

        $this->transport->expects(self::never())->method('send');
        $this->em->expects(self::once())->method('persist')
            ->with(self::callback(fn (NotificationLog $log) => $log->getStatus() === NotificationLogStatus::Throttled));

        ($this->handler)($this->createMessage());
    }

    #[Test]
    public function it_logs_failure_and_rethrows_on_provider_error(): void
    {
        $customer = new Customer('Test Corp');
        $shipment = new Shipment('REF-001', $customer);

        $this->shipmentRepo->method('find')->willReturn($shipment);
        $this->logRepo->method('hasBeenSent')->willReturn(false);
        $this->quietHours->method('canSendNow')->willReturn(true);
        $this->throttle->method('canSend')->willReturn(true);
        $this->quota->method('canSend')->willReturn(true);

        $this->transport->method('send')->willThrowException(new \RuntimeException('Twilio error'));

        $this->em->expects(self::once())->method('persist')
            ->with(self::callback(fn (NotificationLog $log) => $log->getStatus() === NotificationLogStatus::Failed));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Twilio error');

        ($this->handler)($this->createMessage());
    }

    private function createMessage(): SendNotificationMessage
    {
        return new SendNotificationMessage(
            shipmentId: 1,
            channel: NotificationChannel::Sms->value,
            triggerType: NotificationTriggerType::Reminder->value,
            recipientPhone: '+34600000001',
            message: 'Test notification message',
            timing: [],
        );
    }
}
