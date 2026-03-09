<?php

declare(strict_types=1);

namespace App\Application\Fleet;

final readonly class FleetSummary
{
    public function __construct(
        public int $activeRoutes,
        public int $totalVehicles,
        public int $pendingStops,
    ) {}

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'active_routes' => $this->activeRoutes,
            'total_vehicles' => $this->totalVehicles,
            'pending_stops' => $this->pendingStops,
        ];
    }
}
