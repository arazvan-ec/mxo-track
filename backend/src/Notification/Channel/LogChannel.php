<?php

declare(strict_types=1);

namespace App\Notification\Channel;

use App\Notification\RecipientPreference;
use App\Notification\Template\NotificationTemplate;
use Psr\Log\LoggerInterface;

final class LogChannel implements NotificationChannelInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function send(string $recipient, NotificationTemplate $template): bool
    {
        $this->logger->info('Notification [{template}] to {recipient}: {message}', [
            'template' => $template->getTemplateName(),
            'recipient' => $recipient,
            'message' => $template->getSmsText(),
            'subject' => $template->getSubject(),
        ]);

        return true;
    }

    public function supports(string $channelType): bool
    {
        return $channelType === RecipientPreference::CHANNEL_LOG;
    }
}
