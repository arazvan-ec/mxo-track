<?php

declare(strict_types=1);

namespace App\Enum;

enum UserRole: string
{
    case ADMIN = 'ROLE_ADMIN';
    case OPERATOR = 'ROLE_OPERATOR';
    case CUSTOMER = 'ROLE_CUSTOMER';
    case DRIVER = 'ROLE_DRIVER';
}
