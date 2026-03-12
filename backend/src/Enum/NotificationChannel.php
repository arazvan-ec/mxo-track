<?php

declare(strict_types=1);

namespace App\Enum;

enum NotificationChannel: string
{
    case Sms = 'sms';
    case WhatsApp = 'whatsapp';
}
