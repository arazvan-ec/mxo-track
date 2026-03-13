<?php

declare(strict_types=1);

namespace App\Enum;

enum OptimizationOperation: string
{
    case BUILD_ROUTES = 'build_routes';
    case OPTIMIZE_STOPS = 'optimize_stops';
    case REOPTIMIZE_PENDING = 'reoptimize_pending';
    case TEST_ROUTING = 'test_routing';

    public function label(): string
    {
        return match ($this) {
            self::BUILD_ROUTES => 'Construccion de rutas',
            self::OPTIMIZE_STOPS => 'Optimizacion de paradas',
            self::REOPTIMIZE_PENDING => 'Re-optimizacion de pendientes',
            self::TEST_ROUTING => 'Test de routing',
        };
    }
}
