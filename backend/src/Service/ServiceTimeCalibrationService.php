<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * Computes calibrated service times per delivery address based on
 * historical delivery data from completed routes.
 *
 * Service time = time between consecutive deliveries (deliveredAt deltas).
 * For the first stop of a route, uses route startAt as the reference.
 */
final readonly class ServiceTimeCalibrationService
{
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * @return list<array{address: string, avgSeconds: int, sampleCount: int, minSeconds: int, maxSeconds: int}>
     */
    public function getCalibratedServiceTimes(int $customerId, int $limit = 50, int $minSamples = 2): array
    {
        // Uses a window function to compute service time per stop:
        // For each delivered stop, service_time = deliveredAt - LAG(deliveredAt) over the same route.
        // For the first stop in a route, falls back to deliveredAt - route.startAt.
        $sql = <<<'SQL'
            WITH stop_times AS (
                SELECT
                    rs.address,
                    EXTRACT(EPOCH FROM (
                        rs.delivered_at - COALESCE(
                            LAG(rs.delivered_at) OVER (PARTITION BY rs.route_id ORDER BY rs.delivered_at),
                            r.start_at
                        )
                    )) AS service_seconds
                FROM route_stop rs
                INNER JOIN route_plan r ON r.id = rs.route_id
                WHERE r.status = 'done'
                  AND r.customer_id = :customer_id
                  AND rs.delivered_at IS NOT NULL
                  AND rs.is_origin = false
                  AND r.start_at IS NOT NULL
            )
            SELECT
                address,
                AVG(service_seconds) AS avg_seconds,
                COUNT(*) AS sample_count,
                MIN(service_seconds) AS min_seconds,
                MAX(service_seconds) AS max_seconds
            FROM stop_times
            WHERE service_seconds > 0
              AND service_seconds < 3600
            GROUP BY address
            HAVING COUNT(*) >= :min_samples
            ORDER BY COUNT(*) DESC
            LIMIT :limit
            SQL;

        $rows = $this->connection->executeQuery($sql, [
            'customer_id' => $customerId,
            'min_samples' => $minSamples,
            'limit' => $limit,
        ])->fetchAllAssociative();

        return array_map(static fn (array $row) => [
            'address' => $row['address'],
            'avgSeconds' => (int) round((float) $row['avg_seconds']),
            'sampleCount' => (int) $row['sample_count'],
            'minSeconds' => (int) round((float) $row['min_seconds']),
            'maxSeconds' => (int) round((float) $row['max_seconds']),
        ], $rows);
    }
}
