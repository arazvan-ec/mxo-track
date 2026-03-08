<?php

declare(strict_types=1);

namespace App\Notification\Template;

final class PreDeliveryTemplate extends NotificationTemplate
{
    public function __construct(
        private readonly string $recipientName,
        private readonly string $driverName,
        private readonly \DateTimeInterface $estimatedArrival,
        private readonly string $trackingUrl,
    ) {
    }

    public function getTemplateName(): string
    {
        return 'pre_delivery_notification';
    }

    public function getSmsText(): string
    {
        return sprintf(
            'Hola %s, su entrega llega en ~30 minutos. Conductor: %s. Seguimiento: %s',
            $this->recipientName,
            $this->driverName,
            $this->trackingUrl,
        );
    }

    public function getWhatsAppTemplateName(): string
    {
        return 'pre_delivery_notification';
    }

    public function getWhatsAppParameters(): array
    {
        return [
            'recipient_name' => $this->recipientName,
            'driver_name' => $this->driverName,
            'estimated_arrival' => $this->estimatedArrival->format('H:i'),
            'tracking_url' => $this->trackingUrl,
        ];
    }

    public function getSubject(): string
    {
        return 'Su entrega llega pronto';
    }
}
