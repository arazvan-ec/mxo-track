<?php

declare(strict_types=1);

namespace App\Notification\Template;

final class DeliveryCompletedTemplate extends NotificationTemplate
{
    public function __construct(
        private readonly string $recipientName,
        private readonly string $shipmentReference,
        private readonly string $ratingUrl,
    ) {
    }

    public function getTemplateName(): string
    {
        return 'delivery_completed';
    }

    public function getSmsText(): string
    {
        return sprintf(
            'Hola %s, su envío %s ha sido entregado. ¿Cómo fue su experiencia? Califique aquí: %s',
            $this->recipientName,
            $this->shipmentReference,
            $this->ratingUrl,
        );
    }

    public function getWhatsAppTemplateName(): string
    {
        return 'delivery_completed';
    }

    public function getWhatsAppParameters(): array
    {
        return [
            'recipient_name' => $this->recipientName,
            'shipment_reference' => $this->shipmentReference,
            'rating_url' => $this->ratingUrl,
        ];
    }

    public function getSubject(): string
    {
        return 'Su envío ha sido entregado';
    }
}
