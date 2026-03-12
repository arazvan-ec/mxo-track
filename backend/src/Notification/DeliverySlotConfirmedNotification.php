<?php

declare(strict_types=1);

namespace App\Notification;

use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Notification\SmsNotificationInterface;
use Symfony\Component\Notifier\Recipient\SmsRecipientInterface;

final class DeliverySlotConfirmedNotification extends Notification implements SmsNotificationInterface
{
    public function __construct(
        private readonly string $recipientName,
        private readonly string $slotDate,
        private readonly string $slotTimeRange,
    ) {
        parent::__construct('Franja de entrega confirmada');
    }

    public function asSmsMessage(SmsRecipientInterface $recipient, ?string $transport = null): ?SmsMessage
    {
        return new SmsMessage(
            $recipient->getPhone(),
            sprintf(
                'Hola %s, su franja de entrega confirmada: %s %s',
                $this->recipientName,
                $this->slotDate,
                $this->slotTimeRange,
            ),
        );
    }

    public function getTemplateName(): string
    {
        return 'delivery_slot_confirmation';
    }

    public function getChannels(object $recipient): array
    {
        return ['sms'];
    }
}
