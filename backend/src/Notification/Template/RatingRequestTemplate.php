<?php

declare(strict_types=1);

namespace App\Notification\Template;

final class RatingRequestTemplate extends NotificationTemplate
{
    public function __construct(
        private readonly string $recipientName,
        private readonly string $ratingUrl,
    ) {
    }

    public function getTemplateName(): string
    {
        return 'rating_request';
    }

    public function getSmsText(): string
    {
        return sprintf(
            'Hola %s, ¿cómo fue su entrega? Califique aquí: %s',
            $this->recipientName,
            $this->ratingUrl,
        );
    }

    public function getWhatsAppTemplateName(): string
    {
        return 'rating_request';
    }

    public function getWhatsAppParameters(): array
    {
        return [
            'recipient_name' => $this->recipientName,
            'rating_url' => $this->ratingUrl,
        ];
    }

    public function getSubject(): string
    {
        return 'Califique su entrega';
    }
}
