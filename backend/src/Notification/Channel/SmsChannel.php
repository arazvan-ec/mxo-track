<?php

declare(strict_types=1);

namespace App\Notification\Channel;

use App\Notification\Provider\SmsProviderInterface;
use App\Notification\RecipientPreference;
use App\Notification\Template\NotificationTemplate;

final class SmsChannel implements NotificationChannelInterface
{
    public function __construct(
        private readonly SmsProviderInterface $smsProvider,
    ) {
    }

    public function send(string $recipient, NotificationTemplate $template): bool
    {
        return $this->smsProvider->sendSms($recipient, $template->getSmsText());
    }

    public function supports(string $channelType): bool
    {
        return $channelType === RecipientPreference::CHANNEL_SMS;
    }
}
