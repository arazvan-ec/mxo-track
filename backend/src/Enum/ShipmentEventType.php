<?php

declare(strict_types=1);

namespace App\Enum;

enum ShipmentEventType: string
{
    case CREATED = 'CREATED';
    case PICKED_UP = 'PICKED_UP';
    case IN_HUB = 'IN_HUB';
    case IN_TRANSIT = 'IN_TRANSIT';
    case OUT_FOR_DELIVERY = 'OUT_FOR_DELIVERY';
    case DELIVERED = 'DELIVERED';
    case EXCEPTION = 'EXCEPTION';
}
