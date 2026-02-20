<?php

declare(strict_types=1);

namespace App\Enum;

enum RouteStopStatus: string
{
    case PENDING = 'PENDING';
    case DELIVERED = 'DELIVERED';
    case EXCEPTION = 'EXCEPTION';
    case SKIPPED = 'SKIPPED';
}
