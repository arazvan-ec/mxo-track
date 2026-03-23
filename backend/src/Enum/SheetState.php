<?php

declare(strict_types=1);

namespace App\Enum;

enum SheetState: string
{
    case COLLAPSED = 'collapsed';
    case HALF = 'half';
    case FULL = 'full';
}
