<?php

declare(strict_types=1);

namespace App\Notification\Template;

final class RescheduleConfirmationTemplate extends NotificationTemplate
{
    public function __construct(
        private readonly string $recipientName,
        private readonly string $newSlotDate,
        private readonly string $newSlotTimeRange,
        private readonly string $trackingUrl,
    ) {
    }

    public function getTemplateName(): string
    {
        return 'reschedule_confirmation';
    }

    public function getSmsText(): string
    {
        return sprintf(
            'Hola %s, tu entrega ha sido reprogramada para el %s de %s. Sigue tu envio en: %s',
            $this->recipientName,
            $this->newSlotDate,
            $this->newSlotTimeRange,
            $this->trackingUrl,
        );
    }

    public function getWhatsAppTemplateName(): string
    {
        return 'reschedule_confirmation';
    }

    public function getWhatsAppParameters(): array
    {
        return [
            'recipient_name' => $this->recipientName,
            'new_slot_date' => $this->newSlotDate,
            'new_slot_time_range' => $this->newSlotTimeRange,
            'tracking_url' => $this->trackingUrl,
        ];
    }

    public function getSubject(): string
    {
        return 'Entrega reprogramada';
    }
}
