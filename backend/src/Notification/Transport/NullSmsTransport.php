<?php

declare(strict_types=1);

namespace App\Notification\Transport;

use Psr\Log\LoggerInterface;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\TransportInterface;

final class NullSmsTransport implements TransportInterface
{
    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function send(MessageInterface $message): SentMessage
    {
        if ($message instanceof SmsMessage) {
            $this->logger?->info('NullSmsTransport: would send SMS to {phone}: {text}', [
                'phone' => $message->getPhone(),
                'text' => $message->getSubject(),
            ]);
        }

        return new SentMessage($message, (string) $this);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    public function __toString(): string
    {
        return 'null';
    }
}
