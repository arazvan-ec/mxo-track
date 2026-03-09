<?php

declare(strict_types=1);

namespace App\Enum;

enum ParcelStatus: string
{
    case REGISTERED = 'REGISTERED';
    case IN_WAREHOUSE = 'IN_WAREHOUSE';
    case LOADED = 'LOADED';
    case IN_TRANSIT = 'IN_TRANSIT';
    case DELIVERED = 'DELIVERED';
    case ABSENT = 'ABSENT';
    case RETURNED = 'RETURNED';
    case DAMAGED = 'DAMAGED';
    case LOST = 'LOST';

    public function label(): string
    {
        return match ($this) {
            self::REGISTERED => 'Registrado',
            self::IN_WAREHOUSE => 'En almacén',
            self::LOADED => 'Cargado',
            self::IN_TRANSIT => 'En ruta',
            self::DELIVERED => 'Entregado',
            self::ABSENT => 'Ausencia',
            self::RETURNED => 'Devuelto',
            self::DAMAGED => 'Dañado',
            self::LOST => 'Perdido',
        };
    }
}
