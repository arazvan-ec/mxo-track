<?php

declare(strict_types=1);

namespace App\Enum;

enum ClientFrequency: string
{
    case NOT_FREQUENT = 'NOT_FREQUENT';
    case FREQUENT = 'FREQUENT';
    case VERY_FREQUENT = 'VERY_FREQUENT';
    case SUPER_FREQUENT = 'SUPER_FREQUENT';

    public function label(): string
    {
        return match ($this) {
            self::NOT_FREQUENT => 'No frecuente',
            self::FREQUENT => 'Frecuente',
            self::VERY_FREQUENT => 'Muy frecuente',
            self::SUPER_FREQUENT => 'Super frecuente',
        };
    }
}
