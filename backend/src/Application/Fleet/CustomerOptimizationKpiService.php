<?php

declare(strict_types=1);

namespace App\Application\Fleet;

use App\Entity\Customer;
use App\Repository\RoutePerformanceMetricRepository;

final readonly class CustomerOptimizationKpiService
{
    public function __construct(
        private RoutePerformanceMetricRepository $metricRepo,
    ) {}

    public function getOptimizationKpis(Customer $customer): CustomerOptimizationKpis
    {
        $allTime = $this->metricRepo->getCustomerAggregateMetrics(
            $customer,
            new \DateTimeImmutable('1970-01-01'),
        );

        $monthStart = new \DateTimeImmutable('first day of this month midnight');
        $monthly = $this->metricRepo->getCustomerAggregateMetrics($customer, $monthStart);

        return new CustomerOptimizationKpis(
            totalKmSaved: $allTime['total_km_saved'],
            totalTimeSavedMinutes: $allTime['total_time_saved_minutes'],
            avgDeliverySuccessRate: $allTime['avg_delivery_success_rate'],
            avgSavingsPercent: $allTime['avg_savings_percent'],
            routesWithMetrics: $allTime['routes_with_metrics'],
            monthlyKmSaved: $monthly['total_km_saved'],
            monthlyTimeSavedMinutes: $monthly['total_time_saved_minutes'],
        );
    }
}
