<?php

declare(strict_types=1);

namespace App\Notification;

use App\Notification\Channel\NotificationChannelInterface;
use App\Notification\Template\NotificationTemplate;
use Psr\Log\LoggerInterface;

final class RecipientNotificationService
{
    /**
     * @param iterable<NotificationChannelInterface> $channels
     */
    public function __construct(
        private readonly iterable $channels,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function notify(string $recipient, string $channelType, NotificationTemplate $template): bool
    {
        foreach ($this->channels as $channel) {
            if ($channel->supports($channelType)) {
                return $channel->send($recipient, $template);
            }
        }

        $this->logger->warning('No notification channel found for type {type}', [
            'type' => $channelType,
            'template' => $template->getTemplateName(),
        ]);

        return false;
    }
}
