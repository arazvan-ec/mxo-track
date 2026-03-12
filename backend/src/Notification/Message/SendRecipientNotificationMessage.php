<?php

declare(strict_types=1);

namespace App\Notification\Message;

final readonly class SendRecipientNotificationMessage
{
    public function __construct(
        public int $routeStopId,
        public string $notificationType,
        public ?int $customerId = null,
    ) {
    }
}
