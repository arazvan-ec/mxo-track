<?php

declare(strict_types=1);

namespace App\Notification\Message;

use App\Entity\NotificationLog;
use App\Enum\NotificationChannel;
use App\Enum\NotificationLogStatus;
use App\Enum\NotificationTriggerType;
use App\Notification\Gate\CustomerNotificationQuota;
use App\Notification\Gate\QuietHoursGuard;
use App\Notification\Gate\RecipientThrottle;
use App\Notification\Transport\TenantAwareSmsTransport;
use App\Repository\NotificationLogRepository;
use App\Repository\ShipmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Notifier\Message\SmsMessage;

#[AsMessageHandler]
final class SendNotificationHandler
{
    public function __construct(
        private readonly ShipmentRepository $shipmentRepo,
        private readonly NotificationLogRepository $logRepo,
        private readonly TenantAwareSmsTransport $transport,
        private readonly RecipientThrottle $throttle,
        private readonly CustomerNotificationQuota $quota,
        private readonly QuietHoursGuard $quietHours,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {}

    public function __invoke(SendNotificationMessage $message): void
    {
        $shipment = $this->shipmentRepo->find($message->shipmentId);
        if ($shipment === null) {
            return;
        }

        $channel = NotificationChannel::from($message->channel);
        $triggerType = NotificationTriggerType::from($message->triggerType);
        $customer = $shipment->getCustomer();

        // Gate 1: Deduplication
        if ($this->logRepo->hasBeenSent($shipment, $triggerType, $channel)) {
            return;
        }

        // Gate 2: Quiet hours
        if (!$this->quietHours->canSendNow()) {
            $this->persistLog($shipment, $customer, $channel, $triggerType, $message, NotificationLogStatus::Deferred, [
                'reason' => 'quiet_hours',
            ]);

            return;
        }

        // Gate 3: Recipient throttle
        if (!$this->throttle->canSend($message->recipientPhone, $channel)) {
            $this->persistLog($shipment, $customer, $channel, $triggerType, $message, NotificationLogStatus::Throttled, [
                'reason' => 'recipient_rate_limit',
            ]);

            return;
        }

        // Gate 4: Customer quota
        if (!$this->quota->canSend($customer, $channel)) {
            $this->persistLog($shipment, $customer, $channel, $triggerType, $message, NotificationLogStatus::Throttled, [
                'reason' => 'customer_quota_exceeded',
            ]);

            return;
        }

        // Send
        try {
            $this->transport->setCustomer($customer);
            $smsMessage = new SmsMessage($message->recipientPhone, $message->message);
            $this->transport->send($smsMessage);

            $this->persistLog($shipment, $customer, $channel, $triggerType, $message, NotificationLogStatus::Sent);
        } catch (\Throwable $e) {
            $this->persistLog($shipment, $customer, $channel, $triggerType, $message, NotificationLogStatus::Failed, [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            $this->transport->setCustomer(null);
        }
    }

    private function persistLog(
        \App\Entity\Shipment $shipment,
        \App\Entity\Customer $customer,
        NotificationChannel $channel,
        NotificationTriggerType $triggerType,
        SendNotificationMessage $message,
        NotificationLogStatus $status,
        array $providerResponse = [],
    ): void {
        $log = new NotificationLog(
            shipment: $shipment,
            customer: $customer,
            channel: $channel,
            triggerType: $triggerType,
            recipientPhone: $message->recipientPhone,
            messageContent: $message->message,
            status: $status,
            providerResponse: $providerResponse,
        );
        $this->em->persist($log);
        $this->em->flush();
    }
}
