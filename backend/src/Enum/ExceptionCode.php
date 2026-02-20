<?php

declare(strict_types=1);

namespace App\Enum;

enum ExceptionCode: string
{
    case ABSENT = 'ABSENT';
    case WRONG_ADDRESS = 'WRONG_ADDRESS';
    case REFUSED = 'REFUSED';
    case DAMAGED = 'DAMAGED';
    case OTHER = 'OTHER';
}
