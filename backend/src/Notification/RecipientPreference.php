<?php

declare(strict_types=1);

namespace App\Notification;

/**
 * Value object representing a recipient's preferred notification channel.
 */
final readonly class RecipientPreference
{
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_WHATSAPP = 'whatsapp';
    public const CHANNEL_LOG = 'log';

    public function __construct(
        private string $phoneNumber,
        private string $preferredChannel = self::CHANNEL_SMS,
    ) {
    }

    public function getPhoneNumber(): string
    {
        return $this->phoneNumber;
    }

    public function getPreferredChannel(): string
    {
        return $this->preferredChannel;
    }
}
