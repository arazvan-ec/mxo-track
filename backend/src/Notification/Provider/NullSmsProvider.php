<?php

declare(strict_types=1);

namespace App\Notification\Provider;

use Psr\Log\LoggerInterface;

final class NullSmsProvider implements SmsProviderInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function sendSms(string $phoneNumber, string $message): bool
    {
        $this->logger->debug('NullSmsProvider: SMS to {phone} suppressed', [
            'phone' => $phoneNumber,
            'message' => $message,
        ]);

        return true;
    }
}
