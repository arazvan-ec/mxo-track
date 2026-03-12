<?php

declare(strict_types=1);

namespace App\Notification\Message;

final readonly class SendRecipientNotificationMessage
{
    public function __construct(
        public string $routeStopId,
        public string $notificationType,
        public ?string $customerId = null,
    ) {
    }
}
