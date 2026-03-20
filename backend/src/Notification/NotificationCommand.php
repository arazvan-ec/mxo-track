<?php

declare(strict_types=1);

namespace App\Notification;

use App\Domain\Shipment\Model\Shipment;
use App\Enum\NotificationChannel;

final readonly class NotificationCommand
{
    /**
     * @param array<string, int> $timing
     */
    public function __construct(
        public Shipment $shipment,
        public NotificationChannel $channel,
        public string $message,
        public array $timing,
    ) {}
}
