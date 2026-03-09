<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

final class ZonePerformanceService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Get weekly delivery performance trends grouped by delivery zone.
     *
     * @return list<array{zone_name: string, week: string, total_deliveries: int, successful_deliveries: int, exceptions: int, avg_delivery_time_minutes: float, success_rate: float}>
     */
    public function getWeeklyTrends(?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): array
    {
        if ($to === null) {
            $to = new \DateTimeImmutable('now');
        }
        if ($from === null) {
            $from = $to->modify('-8 weeks');
        }

        $conn = $this->em->getConnection();

        $sql = <<<'SQL'
            SELECT
                COALESCE(dz.name, 'Sin zona') AS zone_name,
                TO_CHAR(DATE_TRUNC('week', rs.delivered_at), 'IYYY-"W"IW') AS week,
                COUNT(*) FILTER (WHERE rs.status IN ('DELIVERED', 'EXCEPTION')) AS total_deliveries,
                COUNT(*) FILTER (WHERE rs.status = 'DELIVERED') AS successful_deliveries,
                COUNT(*) FILTER (WHERE rs.status = 'EXCEPTION') AS exceptions,
                COALESCE(
                    AVG(
                        EXTRACT(EPOCH FROM (rs.delivered_at - r.start_at)) / 60
                    ) FILTER (WHERE rs.status = 'DELIVERED' AND rs.delivered_at IS NOT NULL AND r.start_at IS NOT NULL),
                    0
                ) AS avg_delivery_time_minutes
            FROM route_stop rs
            JOIN route_plan r ON rs.route_id = r.id
            LEFT JOIN shipment s ON rs.shipment_id = s.id
            LEFT JOIN delivery_zone dz ON (
                s.latitude IS NOT NULL
                AND s.longitude IS NOT NULL
                AND (
                    6371 * ACOS(
                        LEAST(1.0, GREATEST(-1.0,
                            COS(RADIANS(dz.center_lat)) * COS(RADIANS(s.latitude))
                            * COS(RADIANS(s.longitude) - RADIANS(dz.center_lng))
                            + SIN(RADIANS(dz.center_lat)) * SIN(RADIANS(s.latitude))
                        ))
                    )
                ) <= dz.radius_km
            )
            WHERE rs.is_origin = false
              AND rs.status IN ('DELIVERED', 'EXCEPTION')
              AND rs.delivered_at IS NOT NULL
              AND rs.delivered_at >= :from_date
              AND rs.delivered_at <= :to_date
              AND r.deleted_at IS NULL
            GROUP BY dz.name, DATE_TRUNC('week', rs.delivered_at)
            ORDER BY week ASC, zone_name ASC
        SQL;

        $rows = $conn->fetchAllAssociative($sql, [
            'from_date' => $from->format('Y-m-d H:i:s'),
            'to_date' => $to->format('Y-m-d H:i:s'),
        ]);

        return array_map(static function (array $row): array {
            $total = (int) $row['total_deliveries'];
            $successful = (int) $row['successful_deliveries'];

            return [
                'zone_name' => (string) $row['zone_name'],
                'week' => (string) $row['week'],
                'total_deliveries' => $total,
                'successful_deliveries' => $successful,
                'exceptions' => (int) $row['exceptions'],
                'avg_delivery_time_minutes' => round((float) $row['avg_delivery_time_minutes'], 1),
                'success_rate' => $total > 0 ? round(($successful / $total) * 100, 1) : 0.0,
            ];
        }, $rows);
    }

    /**
     * Get current week aggregate per zone.
     *
     * @return list<array{zone_name: string, total: int, success_rate: float, avg_time: float}>
     */
    public function getZoneSummary(): array
    {
        $conn = $this->em->getConnection();

        $weekStart = new \DateTimeImmutable('monday this week');
        $weekEnd = new \DateTimeImmutable('sunday this week');
        $weekEnd = $weekEnd->setTime(23, 59, 59);

        $sql = <<<'SQL'
            SELECT
                COALESCE(dz.name, 'Sin zona') AS zone_name,
                COUNT(*) FILTER (WHERE rs.status IN ('DELIVERED', 'EXCEPTION')) AS total,
                COUNT(*) FILTER (WHERE rs.status = 'DELIVERED') AS successful,
                COALESCE(
                    AVG(
                        EXTRACT(EPOCH FROM (rs.delivered_at - r.start_at)) / 60
                    ) FILTER (WHERE rs.status = 'DELIVERED' AND rs.delivered_at IS NOT NULL AND r.start_at IS NOT NULL),
                    0
                ) AS avg_time
            FROM route_stop rs
            JOIN route_plan r ON rs.route_id = r.id
            LEFT JOIN shipment s ON rs.shipment_id = s.id
            LEFT JOIN delivery_zone dz ON (
                s.latitude IS NOT NULL
                AND s.longitude IS NOT NULL
                AND (
                    6371 * ACOS(
                        LEAST(1.0, GREATEST(-1.0,
                            COS(RADIANS(dz.center_lat)) * COS(RADIANS(s.latitude))
                            * COS(RADIANS(s.longitude) - RADIANS(dz.center_lng))
                            + SIN(RADIANS(dz.center_lat)) * SIN(RADIANS(s.latitude))
                        ))
                    )
                ) <= dz.radius_km
            )
            WHERE rs.is_origin = false
              AND rs.status IN ('DELIVERED', 'EXCEPTION')
              AND rs.delivered_at IS NOT NULL
              AND rs.delivered_at >= :from_date
              AND rs.delivered_at <= :to_date
              AND r.deleted_at IS NULL
            GROUP BY dz.name
            ORDER BY total DESC
        SQL;

        $rows = $conn->fetchAllAssociative($sql, [
            'from_date' => $weekStart->format('Y-m-d H:i:s'),
            'to_date' => $weekEnd->format('Y-m-d H:i:s'),
        ]);

        return array_map(static function (array $row): array {
            $total = (int) $row['total'];
            $successful = (int) $row['successful'];

            return [
                'zone_name' => (string) $row['zone_name'],
                'total' => $total,
                'success_rate' => $total > 0 ? round(($successful / $total) * 100, 1) : 0.0,
                'avg_time' => round((float) $row['avg_time'], 1),
            ];
        }, $rows);
    }
}
