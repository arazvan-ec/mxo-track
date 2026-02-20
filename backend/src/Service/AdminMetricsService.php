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

    /** @return array<string,int> */
    public function collect(): array
    {
        $now = new DateTimeImmutable();
        $todayStart = $now->setTime(0, 0, 0)->format('Y-m-d H:i:s');
        $hourAgo = $now->modify('-1 hour')->format('Y-m-d H:i:s');

        return [
            'import_runs_today' => $this->countSince('csv_import_run', 'created_at', $todayStart),
            'positions_ingested_last_hour' => $this->countSince('vehicle_positions', 'server_time', $hourAgo),
            'active_routes' => $this->countByValue('route_plan', 'status', 'ACTIVE'),
            'pending_stops' => $this->countByValue('route_stop', 'status', 'PENDING'),
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
}
