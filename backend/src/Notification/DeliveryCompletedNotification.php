<?php

declare(strict_types=1);

namespace App\Notification;

use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Notification\SmsNotificationInterface;
use Symfony\Component\Notifier\Recipient\SmsRecipientInterface;

final class DeliveryCompletedNotification extends Notification implements SmsNotificationInterface
{
    public function __construct(
        private readonly string $recipientName,
        private readonly string $shipmentReference,
        private readonly string $ratingUrl,
    ) {
        parent::__construct('Su envío ha sido entregado');
    }

    public function asSmsMessage(SmsRecipientInterface $recipient, ?string $transport = null): ?SmsMessage
    {
        return new SmsMessage(
            $recipient->getPhone(),
            sprintf(
                'Hola %s, su envío %s ha sido entregado. ¿Cómo fue su experiencia? Califique aquí: %s',
                $this->recipientName,
                $this->shipmentReference,
                $this->ratingUrl,
            ),
        );
    }

    public function getTemplateName(): string
    {
        return 'delivery_completed';
    }

    public function getChannels(object $recipient): array
    {
        return ['sms'];
    }
}
