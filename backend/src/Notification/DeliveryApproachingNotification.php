<?php

declare(strict_types=1);

namespace App\Notification;

use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Notification\SmsNotificationInterface;
use Symfony\Component\Notifier\Recipient\SmsRecipientInterface;

final class DeliveryApproachingNotification extends Notification implements SmsNotificationInterface
{
    public function __construct(
        private readonly string $recipientName,
        private readonly string $driverName,
        private readonly \DateTimeInterface $estimatedArrival,
        private readonly string $trackingUrl,
    ) {
        parent::__construct('Su entrega llega pronto');
    }

    public function asSmsMessage(SmsRecipientInterface $recipient, ?string $transport = null): ?SmsMessage
    {
        $minutesAway = max(1, (int) round(($this->estimatedArrival->getTimestamp() - time()) / 60));

        return new SmsMessage(
            $recipient->getPhone(),
            sprintf(
                'Hola %s, su entrega llega en ~%d minutos. Conductor: %s. Seguimiento: %s',
                $this->recipientName,
                $minutesAway,
                $this->driverName,
                $this->trackingUrl,
            ),
        );
    }

    public function getTemplateName(): string
    {
        return 'pre_delivery_notification';
    }

    public function getChannels(object $recipient): array
    {
        return ['sms'];
    }
}
