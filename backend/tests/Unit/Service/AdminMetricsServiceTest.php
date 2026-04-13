<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\AdminMetricsService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AdminMetricsServiceTest extends TestCase
{
    private Connection&MockObject $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
    }

    public function testCollectReturnsAllExistingKeys(): void
    {
        $this->connection->method('fetchOne')->willReturn('0');
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $service = new AdminMetricsService($this->connection);
        $metrics = $service->collect();

        self::assertArrayHasKey('import_runs_today', $metrics);
        self::assertArrayHasKey('positions_ingested_last_hour', $metrics);
        self::assertArrayHasKey('active_routes', $metrics);
        self::assertArrayHasKey('pending_stops', $metrics);
    }

    public function testCollectReturnsNewEnrichmentKeys(): void
    {
        $this->connection->method('fetchOne')->willReturn('0');
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $service = new AdminMetricsService($this->connection);
        $metrics = $service->collect();

        self::assertArrayHasKey('total_routes', $metrics);
        self::assertArrayHasKey('total_stops', $metrics);
        self::assertArrayHasKey('route_status_breakdown', $metrics);
        self::assertArrayHasKey('stop_status_breakdown', $metrics);
        self::assertArrayHasKey('deliveries_today', $metrics);
        self::assertArrayHasKey('failed_today', $metrics);
        self::assertArrayHasKey('import_runs_last_7d', $metrics);
        self::assertArrayHasKey('positions_last_24h', $metrics);
    }

    public function testBreakdownParsesStatusGroupRows(): void
    {
        $this->connection->method('fetchOne')->willReturn('0');
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql): array {
                if (str_contains($sql, 'route_plan')) {
                    return [
                        ['status' => 'ACTIVE', 'c' => '1'],
                        ['status' => 'PLANNED', 'c' => '3'],
                        ['status' => 'DONE', 'c' => '51'],
                    ];
                }
                if (str_contains($sql, 'route_stop')) {
                    return [
                        ['status' => 'PENDING', 'c' => '16'],
                        ['status' => 'DELIVERED', 'c' => '200'],
                    ];
                }
                return [];
            });

        $service = new AdminMetricsService($this->connection);
        $metrics = $service->collect();

        self::assertIsArray($metrics['route_status_breakdown']);
        self::assertSame(1, $metrics['route_status_breakdown']['ACTIVE']);
        self::assertSame(3, $metrics['route_status_breakdown']['PLANNED']);
        self::assertSame(51, $metrics['route_status_breakdown']['DONE']);

        self::assertIsArray($metrics['stop_status_breakdown']);
        self::assertSame(16, $metrics['stop_status_breakdown']['PENDING']);
        self::assertSame(200, $metrics['stop_status_breakdown']['DELIVERED']);
    }

    public function testScalarCountsAreCastToInt(): void
    {
        $this->connection
            ->method('fetchOne')
            ->willReturnCallback(function (string $sql): string {
                if (str_contains($sql, 'csv_import_run') && str_contains($sql, '>=')) {
                    return '7';
                }
                if (str_contains($sql, 'FROM route_plan') && !str_contains($sql, 'WHERE')) {
                    return '55';
                }
                if (str_contains($sql, 'FROM route_stop') && !str_contains($sql, 'WHERE')) {
                    return '203';
                }
                return '0';
            });
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $service = new AdminMetricsService($this->connection);
        $metrics = $service->collect();

        self::assertSame(55, $metrics['total_routes']);
        self::assertSame(203, $metrics['total_stops']);
        self::assertIsInt($metrics['import_runs_last_7d']);
        self::assertIsInt($metrics['positions_last_24h']);
        self::assertIsInt($metrics['deliveries_today']);
        self::assertIsInt($metrics['failed_today']);
    }
}
