<?php

declare(strict_types=1);

namespace App\Notification\Channel;

use App\Notification\Provider\WhatsAppProviderInterface;
use App\Notification\RecipientPreference;
use App\Notification\Template\NotificationTemplate;

final class WhatsAppChannel implements NotificationChannelInterface
{
    public function __construct(
        private readonly WhatsAppProviderInterface $whatsAppProvider,
    ) {
    }

    public function send(string $recipient, NotificationTemplate $template): bool
    {
        return $this->whatsAppProvider->sendTemplate(
            $recipient,
            $template->getWhatsAppTemplateName(),
            $template->getWhatsAppParameters(),
        );
    }

    public function supports(string $channelType): bool
    {
        return $channelType === RecipientPreference::CHANNEL_WHATSAPP;
    }
}
