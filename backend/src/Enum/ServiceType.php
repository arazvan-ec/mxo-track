<?php

declare(strict_types=1);

namespace App\Enum;

enum ServiceType: string
{
    case DELIVERY = 'DELIVERY';
    case DELIVERY_AND_PICKUP = 'DELIVERY_AND_PICKUP';
    case RETURN = 'RETURN';

    public function label(): string
    {
        return match ($this) {
            self::DELIVERY => 'Entrega',
            self::DELIVERY_AND_PICKUP => 'Entrega y recogida',
            self::RETURN => 'Devolución',
        };
    }
}
