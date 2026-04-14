<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final class AdminMetricsService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array<string, int|array<string,int>> */
    public function collect(): array
    {
        $now = new DateTimeImmutable();
        $todayStart = $now->setTime(0, 0, 0)->format('Y-m-d H:i:s');
        $hourAgo = $now->modify('-1 hour')->format('Y-m-d H:i:s');
        $dayAgo = $now->modify('-24 hours')->format('Y-m-d H:i:s');
        $weekAgo = $now->modify('-7 days')->format('Y-m-d H:i:s');

        return [
            // Existing metrics
            'import_runs_today' => $this->countSince('csv_import_run', 'created_at', $todayStart),
            'positions_ingested_last_hour' => $this->countSince('vehicle_positions', 'server_time', $hourAgo),
            'active_routes' => $this->countByValue('route_plan', 'status', 'ACTIVE'),
            'pending_stops' => $this->countByValue('route_stop', 'status', 'PENDING'),
            // Enrichment metrics
            'total_routes' => $this->countAll('route_plan'),
            'total_stops' => $this->countAll('route_stop'),
            'route_status_breakdown' => $this->countByStatusGroup('route_plan'),
            'stop_status_breakdown' => $this->countByStatusGroup('route_stop'),
            'deliveries_today' => $this->countByValueSince('route_stop', 'status', 'DELIVERED', 'delivered_at', $todayStart),
            'failed_today' => $this->countByValue('route_stop', 'status', 'FAILED'),
            'import_runs_last_7d' => $this->countSince('csv_import_run', 'created_at', $weekAgo),
            'positions_last_24h' => $this->countSince('vehicle_positions', 'server_time', $dayAgo),
        ];
    }

    private function countSince(string $table, string $column, string $from): int
    {
        return (int) $this->connection->fetchOne(
            sprintf('SELECT COUNT(*) FROM %s WHERE %s >= :from', $table, $column),
            ['from' => $from],
        );
    }

    private function countByValue(string $table, string $column, string $value): int
    {
        return (int) $this->connection->fetchOne(
            sprintf('SELECT COUNT(*) FROM %s WHERE %s = :value', $table, $column),
            ['value' => $value],
        );
    }

    private function countAll(string $table): int
    {
        return (int) $this->connection->fetchOne(
            sprintf('SELECT COUNT(*) FROM %s', $table),
        );
    }

    private function countByValueSince(string $table, string $column, string $value, string $sinceColumn, string $from): int
    {
        return (int) $this->connection->fetchOne(
            sprintf(
                'SELECT COUNT(*) FROM %s WHERE %s = :value AND %s >= :from',
                $table,
                $column,
                $sinceColumn,
            ),
            ['value' => $value, 'from' => $from],
        );
    }

    /** @return array<string,int> */
    private function countByStatusGroup(string $table): array
    {
        $rows = $this->connection->fetchAllAssociative(
            sprintf('SELECT status, COUNT(*) AS c FROM %s GROUP BY status', $table),
        );
        $out = [];
        foreach ($rows as $row) {
            $status = isset($row['status']) ? (string) $row['status'] : '';
            if ($status === '') {
                continue;
            }
            $out[$status] = (int) ($row['c'] ?? 0);
        }
        return $out;
    }
}
