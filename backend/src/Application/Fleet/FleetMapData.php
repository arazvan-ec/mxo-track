<?php

declare(strict_types=1);

namespace App\Application\Fleet;

final readonly class FleetMapData
{
    /**
     * @param array<array<string, mixed>> $vehicles
     * @param array<array<string, mixed>> $routes
     */
    public function __construct(
        public array $vehicles,
        public array $routes,
    ) {}
}
