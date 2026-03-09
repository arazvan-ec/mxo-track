<?php

declare(strict_types=1);

namespace App\Notification\Template;

final class DeliverySlotConfirmationTemplate extends NotificationTemplate
{
    public function __construct(
        private readonly string $recipientName,
        private readonly string $slotDate,
        private readonly string $slotTimeRange,
    ) {
    }

    public function getTemplateName(): string
    {
        return 'delivery_slot_confirmation';
    }

    public function getSmsText(): string
    {
        return sprintf(
            'Hola %s, su franja de entrega confirmada: %s %s',
            $this->recipientName,
            $this->slotDate,
            $this->slotTimeRange,
        );
    }

    public function getWhatsAppTemplateName(): string
    {
        return 'delivery_slot_confirmation';
    }

    public function getWhatsAppParameters(): array
    {
        return [
            'recipient_name' => $this->recipientName,
            'slot_date' => $this->slotDate,
            'slot_time_range' => $this->slotTimeRange,
        ];
    }

    public function getSubject(): string
    {
        return 'Franja de entrega confirmada';
    }
}
