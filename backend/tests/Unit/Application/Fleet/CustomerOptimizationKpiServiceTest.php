<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Fleet;

use App\Application\Fleet\CustomerOptimizationKpiService;
use App\Application\Fleet\CustomerOptimizationKpis;
use App\Entity\Customer;
use App\Repository\RoutePerformanceMetricRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CustomerOptimizationKpiServiceTest extends TestCase
{
    private RoutePerformanceMetricRepository&MockObject $repo;
    private CustomerOptimizationKpiService $service;
    private Customer $customer;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(RoutePerformanceMetricRepository::class);
        $this->service = new CustomerOptimizationKpiService($this->repo);
        $this->customer = $this->createMock(Customer::class);
    }

    public function testGetOptimizationKpisReturnsDtoWithAllTimeAndMonthly(): void
    {
        $allTimeData = [
            'total_km_saved' => '152.30',
            'total_time_saved_minutes' => 480,
            'avg_delivery_success_rate' => '94.5',
            'avg_savings_percent' => '23.1',
            'routes_with_metrics' => 12,
        ];

        $monthlyData = [
            'total_km_saved' => '45.70',
            'total_time_saved_minutes' => 120,
            'avg_delivery_success_rate' => '96.0',
            'avg_savings_percent' => '25.0',
            'routes_with_metrics' => 3,
        ];

        $this->repo->expects($this->exactly(2))
            ->method('getCustomerAggregateMetrics')
            ->willReturnCallback(function (Customer $customer, \DateTimeImmutable $since) use ($allTimeData, $monthlyData) {
                // First call is all-time (epoch), second is monthly
                if ($since->format('Y') < '2000') {
                    return $allTimeData;
                }

                return $monthlyData;
            });

        $result = $this->service->getOptimizationKpis($this->customer);

        $this->assertInstanceOf(CustomerOptimizationKpis::class, $result);
        $this->assertSame('152.30', $result->totalKmSaved);
        $this->assertSame(480, $result->totalTimeSavedMinutes);
        $this->assertSame('94.5', $result->avgDeliverySuccessRate);
        $this->assertSame('23.1', $result->avgSavingsPercent);
        $this->assertSame(12, $result->routesWithMetrics);
        $this->assertSame('45.70', $result->monthlyKmSaved);
        $this->assertSame(120, $result->monthlyTimeSavedMinutes);
    }

    public function testGetOptimizationKpisHandlesNullMetrics(): void
    {
        $emptyData = [
            'total_km_saved' => null,
            'total_time_saved_minutes' => null,
            'avg_delivery_success_rate' => null,
            'avg_savings_percent' => null,
            'routes_with_metrics' => 0,
        ];

        $this->repo->method('getCustomerAggregateMetrics')->willReturn($emptyData);

        $result = $this->service->getOptimizationKpis($this->customer);

        $this->assertNull($result->totalKmSaved);
        $this->assertNull($result->totalTimeSavedMinutes);
        $this->assertNull($result->avgDeliverySuccessRate);
        $this->assertNull($result->avgSavingsPercent);
        $this->assertSame(0, $result->routesWithMetrics);
        $this->assertNull($result->monthlyKmSaved);
        $this->assertNull($result->monthlyTimeSavedMinutes);
    }

    public function testToArrayReturnsExpectedFormat(): void
    {
        $kpis = new CustomerOptimizationKpis(
            totalKmSaved: '100.00',
            totalTimeSavedMinutes: 300,
            avgDeliverySuccessRate: '95.0',
            avgSavingsPercent: '20.0',
            routesWithMetrics: 10,
            monthlyKmSaved: '30.00',
            monthlyTimeSavedMinutes: 90,
        );

        $array = $kpis->toArray();

        $this->assertSame('100.00', $array['total_km_saved']);
        $this->assertSame(300, $array['total_time_saved_minutes']);
        $this->assertSame('95.0', $array['avg_delivery_success_rate']);
        $this->assertSame('20.0', $array['avg_savings_percent']);
        $this->assertSame(10, $array['routes_with_metrics']);
        $this->assertSame('30.00', $array['monthly_km_saved']);
        $this->assertSame(90, $array['monthly_time_saved_minutes']);
    }
}
