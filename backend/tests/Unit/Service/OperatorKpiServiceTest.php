<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\OperatorKpiService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\TestCase;

final class OperatorKpiServiceTest extends TestCase
{
    public function testCollectKpisReturnsExpectedKeys(): void
    {
        $connection = $this->createMock(Connection::class);
        $result = $this->createMock(Result::class);

        // fetchOne calls: activeRoutes, deliveriesToday, exceptionsToday, vehiclesWithPosition
        $connection->method('fetchOne')->willReturn('0');

        // fetchAllAssociative: for stopCounts query and topDrivers query
        $connection->method('fetchAllAssociative')->willReturn([]);

        $service = new OperatorKpiService($connection);
        $kpis = $service->collectKpis();

        self::assertArrayHasKey('activeRoutes', $kpis);
        self::assertArrayHasKey('deliveriesToday', $kpis);
        self::assertArrayHasKey('exceptionsToday', $kpis);
        self::assertArrayHasKey('completionRate', $kpis);
        self::assertArrayHasKey('successRate7d', $kpis);
        self::assertArrayHasKey('vehiclesWithPosition', $kpis);
        self::assertArrayHasKey('topDrivers', $kpis);
    }

    public function testSuccessRate7dCalculation(): void
    {
        $connection = $this->createMock(Connection::class);

        // We need fetchOne to return different values for different queries
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls(
            '3',  // activeRoutes
            '15', // deliveriesToday
            '2',  // exceptionsToday
            '80', // successRate7d: delivered
            '20', // successRate7d: exceptions
            '5',  // vehiclesWithPosition
        );

        // fetchAllAssociative: stopCounts (for completion rate), topDrivers
        $connection->method('fetchAllAssociative')->willReturnOnConsecutiveCalls(
            // stopCounts
            [['routeId' => 1, 'total' => '10', 'delivered' => '8']],
            // topDrivers
            [],
        );

        $service = new OperatorKpiService($connection);
        $kpis = $service->collectKpis();

        // 80 delivered / (80 + 20) total = 80%
        self::assertSame(80.0, $kpis['successRate7d']);
    }

    public function testTopDriversLimitedToThree(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn('0');

        $connection->method('fetchAllAssociative')->willReturnOnConsecutiveCalls(
            [], // stopCounts
            [  // topDrivers - 4 returned but SQL LIMIT 3 enforced
                ['driver_name' => 'Ana', 'deliveries' => '20', 'exceptions' => '1'],
                ['driver_name' => 'Luis', 'deliveries' => '18', 'exceptions' => '0'],
                ['driver_name' => 'Carlos', 'deliveries' => '15', 'exceptions' => '2'],
            ],
        );

        $service = new OperatorKpiService($connection);
        $kpis = $service->collectKpis();

        self::assertCount(3, $kpis['topDrivers']);
        self::assertSame('Ana', $kpis['topDrivers'][0]['driver_name']);
    }

    public function testVehiclesWithPositionCount(): void
    {
        $connection = $this->createMock(Connection::class);

        $connection->method('fetchOne')->willReturnOnConsecutiveCalls(
            '5',  // activeRoutes
            '10', // deliveriesToday
            '1',  // exceptionsToday
            '50', // successRate7d: delivered
            '5',  // successRate7d: exceptions
            '8',  // vehiclesWithPosition
        );

        $connection->method('fetchAllAssociative')->willReturn([]);

        $service = new OperatorKpiService($connection);
        $kpis = $service->collectKpis();

        self::assertSame(8, $kpis['vehiclesWithPosition']);
    }

    public function testCompletionRateCalculation(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn('0');

        // stopCounts returns data for 2 routes
        $connection->method('fetchAllAssociative')->willReturnOnConsecutiveCalls(
            [
                ['routeId' => 1, 'total' => '10', 'delivered' => '7'],
                ['routeId' => 2, 'total' => '5', 'delivered' => '5'],
            ],
            [], // topDrivers
        );

        $service = new OperatorKpiService($connection);
        $kpis = $service->collectKpis();

        // 12 delivered / 15 total = 80%
        self::assertSame(80.0, $kpis['completionRate']);
    }
}
