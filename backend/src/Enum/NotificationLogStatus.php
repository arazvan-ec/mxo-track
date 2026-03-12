<?php

declare(strict_types=1);

namespace App\Enum;

enum NotificationLogStatus: string
{
    case Sent = 'sent';
    case Failed = 'failed';
    case Throttled = 'throttled';
    case Deferred = 'deferred';
}
