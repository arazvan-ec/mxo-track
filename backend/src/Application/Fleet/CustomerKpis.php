<?php

declare(strict_types=1);

namespace App\Application\Fleet;

final readonly class CustomerKpis
{
    public function __construct(
        public int $totalShipments,
        public int $activeRoutes,
        public int $pendingDeliveries,
        public int $completedToday,
        public int $exceptions,
    ) {}

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'total_shipments' => $this->totalShipments,
            'active_routes' => $this->activeRoutes,
            'pending_deliveries' => $this->pendingDeliveries,
            'completed_today' => $this->completedToday,
            'exceptions' => $this->exceptions,
        ];
    }
}
