<?php

declare(strict_types=1);

namespace App\Enum;

enum ShipmentPriority: int
{
    case LOW = 0;
    case NORMAL = 25;
    case HIGH = 50;
    case URGENT = 75;
    case CRITICAL = 100;

    public function toVroomPriority(): int
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Baja',
            self::NORMAL => 'Normal',
            self::HIGH => 'Alta',
            self::URGENT => 'Urgente',
            self::CRITICAL => 'Crítica',
        };
    }
}
