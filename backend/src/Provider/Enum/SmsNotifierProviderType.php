<?php

declare(strict_types=1);

namespace App\Provider\Enum;

enum SmsNotifierProviderType: string
{
    case Twilio = 'twilio';
    case Null = 'null';
}
