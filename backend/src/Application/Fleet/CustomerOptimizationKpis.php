<?php

declare(strict_types=1);

namespace App\Application\Fleet;

final readonly class CustomerOptimizationKpis
{
    public function __construct(
        public ?string $totalKmSaved,
        public ?int $totalTimeSavedMinutes,
        public ?string $avgDeliverySuccessRate,
        public ?string $avgSavingsPercent,
        public int $routesWithMetrics,
        public ?string $monthlyKmSaved,
        public ?int $monthlyTimeSavedMinutes,
    ) {}

    /**
     * @return array<string, string|int|null>
     */
    public function toArray(): array
    {
        return [
            'total_km_saved' => $this->totalKmSaved,
            'total_time_saved_minutes' => $this->totalTimeSavedMinutes,
            'avg_delivery_success_rate' => $this->avgDeliverySuccessRate,
            'avg_savings_percent' => $this->avgSavingsPercent,
            'routes_with_metrics' => $this->routesWithMetrics,
            'monthly_km_saved' => $this->monthlyKmSaved,
            'monthly_time_saved_minutes' => $this->monthlyTimeSavedMinutes,
        ];
    }
}
