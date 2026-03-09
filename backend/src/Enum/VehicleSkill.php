<?php

declare(strict_types=1);

namespace App\Enum;

enum VehicleSkill: int
{
    case REFRIGERATED = 1;
    case HEAVY_LOAD = 2;
    case PEDESTRIAN_ACCESS = 3;
    case HAZMAT = 4;
    case FRAGILE = 5;

    public function label(): string
    {
        return match ($this) {
            self::REFRIGERATED => 'Transporte refrigerado',
            self::HEAVY_LOAD => 'Carga pesada',
            self::PEDESTRIAN_ACCESS => 'Acceso peatonal',
            self::HAZMAT => 'Materiales peligrosos',
            self::FRAGILE => 'Artículos frágiles',
        };
    }
}
