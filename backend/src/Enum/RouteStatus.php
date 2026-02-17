<?php

declare(strict_types=1);

namespace App\Enum;

enum RouteStatus: string
{
    case PLANNED = 'PLANNED';
    case ACTIVE = 'ACTIVE';
    case DONE = 'DONE';
    case CANCELLED = 'CANCELLED';
}
