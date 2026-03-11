<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

final class OperatorKpiService
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    /**
     * @return array{
     *     activeRoutes: int,
     *     deliveriesToday: int,
     *     exceptionsToday: int,
     *     completionRate: float,
     *     successRate7d: float,
     *     vehiclesWithPosition: int,
     *     topDrivers: list<array{driver_name: string, deliveries: int, exceptions: int, success_rate: float}>,
     * }
     */
    public function collectKpis(): array
    {
        $todayStart = (new \DateTimeImmutable('today midnight'))->format('Y-m-d H:i:s');
        $sevenDaysAgo = (new \DateTimeImmutable('-7 days midnight'))->format('Y-m-d H:i:s');

        $activeRoutes = $this->countActiveRoutes();
        $deliveriesToday = $this->countDeliveriesToday($todayStart);
        $exceptionsToday = $this->countExceptionsToday();
        $completionRate = $this->calculateCompletionRate();
        $successRate7d = $this->calculateSuccessRate7d($sevenDaysAgo);
        $vehiclesWithPosition = $this->countVehiclesWithPosition();
        $topDrivers = $this->getTopDrivers($sevenDaysAgo);

        return [
            'activeRoutes' => $activeRoutes,
            'deliveriesToday' => $deliveriesToday,
            'exceptionsToday' => $exceptionsToday,
            'completionRate' => $completionRate,
            'successRate7d' => $successRate7d,
            'vehiclesWithPosition' => $vehiclesWithPosition,
            'topDrivers' => $topDrivers,
        ];
    }

    private function countActiveRoutes(): int
    {
        return (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM route_plan WHERE status IN ('ACTIVE', 'PLANNED') AND deleted_at IS NULL",
        );
    }

    private function countDeliveriesToday(string $todayStart): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM route_stop WHERE status = :status AND delivered_at >= :today',
            ['status' => 'DELIVERED', 'today' => $todayStart],
        );
    }

    private function countExceptionsToday(): int
    {
        return (int) $this->connection->fetchOne(
            <<<'SQL'
                SELECT COUNT(*)
                FROM route_stop rs
                JOIN route_plan r ON rs.route_id = r.id
                WHERE rs.status = 'EXCEPTION'
                  AND r.status IN ('ACTIVE', 'PLANNED', 'DONE')
                  AND r.deleted_at IS NULL
            SQL,
        );
    }

    private function calculateCompletionRate(): float
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT
                    rs.route_id AS "routeId",
                    COUNT(rs.id) AS total,
                    SUM(CASE WHEN rs.status = 'DELIVERED' THEN 1 ELSE 0 END) AS delivered
                FROM route_stop rs
                JOIN route_plan r ON rs.route_id = r.id
                WHERE r.status IN ('ACTIVE', 'PLANNED')
                  AND r.deleted_at IS NULL
                  AND rs.is_origin = false
                GROUP BY rs.route_id
            SQL,
        );

        $totalStops = 0;
        $totalDelivered = 0;
        foreach ($rows as $row) {
            $totalStops += (int) $row['total'];
            $totalDelivered += (int) $row['delivered'];
        }

        return $totalStops > 0 ? round(($totalDelivered / $totalStops) * 100, 1) : 0.0;
    }

    private function calculateSuccessRate7d(string $sevenDaysAgo): float
    {
        $delivered = (int) $this->connection->fetchOne(
            <<<'SQL'
                SELECT COUNT(*)
                FROM route_stop rs
                JOIN route_plan r ON rs.route_id = r.id
                WHERE rs.status = 'DELIVERED'
                  AND rs.is_origin = false
                  AND r.start_at >= :since
                  AND r.deleted_at IS NULL
            SQL,
            ['since' => $sevenDaysAgo],
        );

        $exceptions = (int) $this->connection->fetchOne(
            <<<'SQL'
                SELECT COUNT(*)
                FROM route_stop rs
                JOIN route_plan r ON rs.route_id = r.id
                WHERE rs.status = 'EXCEPTION'
                  AND rs.is_origin = false
                  AND r.start_at >= :since
                  AND r.deleted_at IS NULL
            SQL,
            ['since' => $sevenDaysAgo],
        );

        $total = $delivered + $exceptions;

        return $total > 0 ? round(($delivered / $total) * 100, 1) : 0.0;
    }

    private function countVehiclesWithPosition(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM vehicle_last_position',
        );
    }

    /**
     * @return list<array{driver_name: string, deliveries: int, exceptions: int, success_rate: float}>
     */
    private function getTopDrivers(string $sevenDaysAgo): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT
                    COALESCE(u.name, u.email) AS driver_name,
                    COUNT(*) FILTER (WHERE rs.status = 'DELIVERED') AS deliveries,
                    COUNT(*) FILTER (WHERE rs.status = 'EXCEPTION') AS exceptions
                FROM route_stop rs
                JOIN route_plan r ON rs.route_id = r.id
                JOIN "user" u ON r.driver_id = u.id
                WHERE rs.is_origin = false
                  AND r.start_at >= :since
                  AND r.deleted_at IS NULL
                GROUP BY u.id, u.name, u.email
                ORDER BY deliveries DESC
                LIMIT 3
            SQL,
            ['since' => $sevenDaysAgo],
        );

        return array_map(function (array $row): array {
            $deliveries = (int) $row['deliveries'];
            $exceptions = (int) $row['exceptions'];
            $total = $deliveries + $exceptions;

            return [
                'driver_name' => (string) $row['driver_name'],
                'deliveries' => $deliveries,
                'exceptions' => $exceptions,
                'success_rate' => $total > 0 ? round(($deliveries / $total) * 100, 1) : 0.0,
            ];
        }, $rows);
    }
}
