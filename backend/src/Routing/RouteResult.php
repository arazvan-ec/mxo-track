<?php

declare(strict_types=1);

namespace App\Routing;

final readonly class RouteResult
{
    public function __construct(
        public float $distanceKm,
        public float $durationSeconds,
    ) {
    }
}
