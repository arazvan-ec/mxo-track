<?php

declare(strict_types=1);

namespace App\Notification\Message;

final readonly class PreDeliveryNotificationMessage
{
    public function __construct(
        private string $shipmentPublicId,
        private string $recipientPhone,
        private \DateTimeImmutable $estimatedArrival,
    ) {
    }

    public function getShipmentPublicId(): string
    {
        return $this->shipmentPublicId;
    }

    public function getRecipientPhone(): string
    {
        return $this->recipientPhone;
    }

    public function getEstimatedArrival(): \DateTimeImmutable
    {
        return $this->estimatedArrival;
    }
}
