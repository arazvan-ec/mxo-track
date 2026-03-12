<?php

declare(strict_types=1);

namespace App\Notification\Message;

final readonly class SendRecipientNotificationMessage
{
    /**
     * @param array<string, string> $metadata Extra notification-specific data (e.g. slot_date, slot_time_range)
     */
    public function __construct(
        public string $routeStopId,
        public string $notificationType,
        public ?string $customerId = null,
        public array $metadata = [],
    ) {
    }
}
