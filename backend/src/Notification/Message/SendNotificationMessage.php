<?php

declare(strict_types=1);

namespace App\Notification\Message;

final readonly class SendNotificationMessage
{
    /**
     * @param array<string, int> $timing
     */
    public function __construct(
        public int $shipmentId,
        public string $channel,
        public string $triggerType,
        public string $recipientPhone,
        public string $message,
        public array $timing,
    ) {}
}
