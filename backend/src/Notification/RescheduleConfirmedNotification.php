<?php

declare(strict_types=1);

namespace App\Notification;

use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Notification\SmsNotificationInterface;
use Symfony\Component\Notifier\Recipient\SmsRecipientInterface;

final class RescheduleConfirmedNotification extends Notification implements SmsNotificationInterface
{
    public function __construct(
        private readonly string $recipientName,
        private readonly string $newSlotDate,
        private readonly string $newSlotTimeRange,
        private readonly string $trackingUrl,
    ) {
        parent::__construct('Entrega reprogramada');
    }

    public function asSmsMessage(SmsRecipientInterface $recipient, ?string $transport = null): ?SmsMessage
    {
        return new SmsMessage(
            $recipient->getPhone(),
            sprintf(
                'Hola %s, tu entrega ha sido reprogramada para el %s de %s. Sigue tu envio en: %s',
                $this->recipientName,
                $this->newSlotDate,
                $this->newSlotTimeRange,
                $this->trackingUrl,
            ),
        );
    }

    public function getTemplateName(): string
    {
        return 'reschedule_confirmation';
    }

    public function getChannels(object $recipient): array
    {
        return ['sms'];
    }
}
