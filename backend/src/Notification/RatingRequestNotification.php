<?php

declare(strict_types=1);

namespace App\Notification;

use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Notification\SmsNotificationInterface;
use Symfony\Component\Notifier\Recipient\SmsRecipientInterface;

final class RatingRequestNotification extends Notification implements SmsNotificationInterface
{
    public function __construct(
        private readonly string $recipientName,
        private readonly string $ratingUrl,
    ) {
        parent::__construct('Califique su entrega');
    }

    public function asSmsMessage(SmsRecipientInterface $recipient, ?string $transport = null): ?SmsMessage
    {
        return new SmsMessage(
            $recipient->getPhone(),
            sprintf(
                'Hola %s, ¿cómo fue su entrega? Califique aquí: %s',
                $this->recipientName,
                $this->ratingUrl,
            ),
        );
    }

    public function getTemplateName(): string
    {
        return 'rating_request';
    }

    public function getChannels(object $recipient): array
    {
        return ['sms'];
    }
}
