<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Customer;
use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteStop;
use App\Entity\ShipmentEvent;
use App\Enum\RouteStatus;
use App\Enum\RouteStopStatus;
use App\Enum\ShipmentEventType;
use Doctrine\ORM\EntityManagerInterface;

final class SlaMetricsService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Calculate SLA metrics for a given date range and optional customer filter.
     *
     * @return array{
     *     otif_rate: float,
     *     on_time_rate: float,
     *     first_attempt_rate: float,
     *     avg_delivery_time_minutes: float,
     *     avg_stops_per_hour: float,
     *     exception_rate_by_type: list<array{code: string, count: int, rate: float}>,
     *     sla_trend: list<array{period_label: string, otif_rate: float, on_time_rate: float, first_attempt_rate: float}>,
     *     driver_ranking: list<array{driver_name: string, driver_email: string, deliveries: int, exceptions: int, success_rate: float, avg_stops_per_hour: float}>
     * }
     */
    public function calculateSla(
        ?Customer $customer,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): array {
        return [
            'otif_rate' => $this->calculateOtifRate($customer, $from, $to),
            'on_time_rate' => $this->calculateOnTimeRate($customer, $from, $to),
            'first_attempt_rate' => $this->calculateFirstAttemptRate($customer, $from, $to),
            'avg_delivery_time_minutes' => $this->calculateAvgDeliveryTimeMinutes($customer, $from, $to),
            'avg_stops_per_hour' => $this->calculateAvgStopsPerHour($customer, $from, $to),
            'exception_rate_by_type' => $this->getExceptionRateByType($customer, $from, $to),
            'sla_trend' => $this->getSlasTrend($customer, $from, $to),
            'driver_ranking' => $this->getDriverSlaRanking($customer, $from, $to),
        ];
    }

    /**
     * OTIF = On Time In Full: stops delivered within delivery window / total completed stops.
     */
    private function calculateOtifRate(
        ?Customer $customer,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): float {
        $conn = $this->em->getConnection();

        $sql = <<<'SQL'
            SELECT
                COUNT(*) FILTER (WHERE rs.status = 'DELIVERED') AS total_delivered,
                COUNT(*) FILTER (
                    WHERE rs.status = 'DELIVERED'
                    AND (
                        rs.delivery_window_end IS NULL
                        OR rs.delivered_at::time <= rs.delivery_window_end
                    )
                ) AS on_time_delivered
            FROM route_stop rs
            JOIN route_plan r ON rs.route_id = r.id
            WHERE rs.is_origin = false
              AND r.start_at >= :from_date
              AND r.start_at <= :to_date
              AND r.deleted_at IS NULL
        SQL;

        $params = ['from_date' => $from->format('Y-m-d H:i:s'), 'to_date' => $to->format('Y-m-d H:i:s')];

        if ($customer !== null) {
            $sql .= ' AND r.customer_id = :customer_id';
            $params['customer_id'] = $customer->getId();
        }

        $result = $conn->fetchAssociative($sql, $params);

        $totalDelivered = (int) ($result['total_delivered'] ?? 0);
        $onTimeDelivered = (int) ($result['on_time_delivered'] ?? 0);

        return $totalDelivered > 0 ? round(($onTimeDelivered / $totalDelivered) * 100, 1) : 0.0;
    }

    /**
     * On-time rate: delivered stops within window / all completed stops (delivered + exception).
     */
    private function calculateOnTimeRate(
        ?Customer $customer,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): float {
        $conn = $this->em->getConnection();

        $sql = <<<'SQL'
            SELECT
                COUNT(*) FILTER (WHERE rs.status IN ('DELIVERED', 'EXCEPTION')) AS total_completed,
                COUNT(*) FILTER (
                    WHERE rs.status = 'DELIVERED'
                    AND (
                        rs.delivery_window_end IS NULL
                        OR rs.delivered_at::time <= rs.delivery_window_end
                    )
                ) AS on_time
            FROM route_stop rs
            JOIN route_plan r ON rs.route_id = r.id
            WHERE rs.is_origin = false
              AND r.start_at >= :from_date
              AND r.start_at <= :to_date
              AND r.deleted_at IS NULL
        SQL;

        $params = ['from_date' => $from->format('Y-m-d H:i:s'), 'to_date' => $to->format('Y-m-d H:i:s')];

        if ($customer !== null) {
            $sql .= ' AND r.customer_id = :customer_id';
            $params['customer_id'] = $customer->getId();
        }

        $result = $conn->fetchAssociative($sql, $params);

        $totalCompleted = (int) ($result['total_completed'] ?? 0);
        $onTime = (int) ($result['on_time'] ?? 0);

        return $totalCompleted > 0 ? round(($onTime / $totalCompleted) * 100, 1) : 0.0;
    }

    /**
     * First attempt delivery rate: delivered stops that were never an exception / total delivered.
     * A stop delivered on first attempt has no prior exception events for its shipment.
     */
    private function calculateFirstAttemptRate(
        ?Customer $customer,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): float {
        $conn = $this->em->getConnection();

        $sql = <<<'SQL'
            SELECT
                COUNT(*) AS total_delivered,
                COUNT(*) FILTER (
                    WHERE NOT EXISTS (
                        SELECT 1 FROM shipment_event se
                        WHERE se.shipment_id = rs.shipment_id
                          AND se.event_type = 'EXCEPTION'
                          AND se.created_at < rs.delivered_at
                    )
                ) AS first_attempt
            FROM route_stop rs
            JOIN route_plan r ON rs.route_id = r.id
            WHERE rs.is_origin = false
              AND rs.status = 'DELIVERED'
              AND rs.shipment_id IS NOT NULL
              AND r.start_at >= :from_date
              AND r.start_at <= :to_date
              AND r.deleted_at IS NULL
        SQL;

        $params = ['from_date' => $from->format('Y-m-d H:i:s'), 'to_date' => $to->format('Y-m-d H:i:s')];

        if ($customer !== null) {
            $sql .= ' AND r.customer_id = :customer_id';
            $params['customer_id'] = $customer->getId();
        }

        $result = $conn->fetchAssociative($sql, $params);

        $totalDelivered = (int) ($result['total_delivered'] ?? 0);
        $firstAttempt = (int) ($result['first_attempt'] ?? 0);

        return $totalDelivered > 0 ? round(($firstAttempt / $totalDelivered) * 100, 1) : 0.0;
    }

    /**
     * Average delivery time in minutes (from route start to individual stop delivery).
     */
    private function calculateAvgDeliveryTimeMinutes(
        ?Customer $customer,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): float {
        $conn = $this->em->getConnection();

        $sql = <<<'SQL'
            SELECT
                AVG(EXTRACT(EPOCH FROM (rs.delivered_at - r.start_at)) / 60) AS avg_minutes
            FROM route_stop rs
            JOIN route_plan r ON rs.route_id = r.id
            WHERE rs.is_origin = false
              AND rs.status = 'DELIVERED'
              AND rs.delivered_at IS NOT NULL
              AND r.start_at IS NOT NULL
              AND r.start_at >= :from_date
              AND r.start_at <= :to_date
              AND r.deleted_at IS NULL
        SQL;

        $params = ['from_date' => $from->format('Y-m-d H:i:s'), 'to_date' => $to->format('Y-m-d H:i:s')];

        if ($customer !== null) {
            $sql .= ' AND r.customer_id = :customer_id';
            $params['customer_id'] = $customer->getId();
        }

        $result = $conn->fetchAssociative($sql, $params);

        return round((float) ($result['avg_minutes'] ?? 0), 1);
    }

    /**
     * Average delivered stops per hour across completed routes.
     */
    private function calculateAvgStopsPerHour(
        ?Customer $customer,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): float {
        $conn = $this->em->getConnection();

        $sql = <<<'SQL'
            SELECT
                SUM(delivered_stops) AS total_delivered,
                SUM(route_hours) AS total_hours
            FROM (
                SELECT
                    COUNT(*) FILTER (WHERE rs.status = 'DELIVERED') AS delivered_stops,
                    EXTRACT(EPOCH FROM (r.end_at - r.start_at)) / 3600.0 AS route_hours
                FROM route_plan r
                JOIN route_stop rs ON rs.route_id = r.id AND rs.is_origin = false
                WHERE r.status = 'DONE'
                  AND r.start_at IS NOT NULL
                  AND r.end_at IS NOT NULL
                  AND r.start_at >= :from_date
                  AND r.start_at <= :to_date
                  AND r.deleted_at IS NULL
        SQL;

        $params = ['from_date' => $from->format('Y-m-d H:i:s'), 'to_date' => $to->format('Y-m-d H:i:s')];

        if ($customer !== null) {
            $sql .= ' AND r.customer_id = :customer_id';
            $params['customer_id'] = $customer->getId();
        }

        $sql .= ' GROUP BY r.id, r.start_at, r.end_at) sub';

        $result = $conn->fetchAssociative($sql, $params);

        $totalDelivered = (float) ($result['total_delivered'] ?? 0);
        $totalHours = (float) ($result['total_hours'] ?? 0);

        return $totalHours > 0 ? round($totalDelivered / $totalHours, 1) : 0.0;
    }

    /**
     * Exception rate breakdown by exception code.
     *
     * @return list<array{code: string, count: int, rate: float}>
     */
    private function getExceptionRateByType(
        ?Customer $customer,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): array {
        $conn = $this->em->getConnection();

        $sql = <<<'SQL'
            SELECT
                COALESCE(rs.exception_code, 'SIN_CODIGO') AS code,
                COUNT(*) AS cnt
            FROM route_stop rs
            JOIN route_plan r ON rs.route_id = r.id
            WHERE rs.is_origin = false
              AND rs.status = 'EXCEPTION'
              AND r.start_at >= :from_date
              AND r.start_at <= :to_date
              AND r.deleted_at IS NULL
        SQL;

        $params = ['from_date' => $from->format('Y-m-d H:i:s'), 'to_date' => $to->format('Y-m-d H:i:s')];

        if ($customer !== null) {
            $sql .= ' AND r.customer_id = :customer_id';
            $params['customer_id'] = $customer->getId();
        }

        $sql .= ' GROUP BY rs.exception_code ORDER BY cnt DESC';

        $rows = $conn->fetchAllAssociative($sql, $params);

        $total = 0;
        foreach ($rows as $row) {
            $total += (int) $row['cnt'];
        }

        return array_map(fn(array $row) => [
            'code' => (string) $row['code'],
            'count' => (int) $row['cnt'],
            'rate' => $total > 0 ? round(((int) $row['cnt'] / $total) * 100, 1) : 0.0,
        ], $rows);
    }

    /**
     * SLA trend data: weekly buckets of OTIF, on-time, and first-attempt rates.
     *
     * @return list<array{period_label: string, otif_rate: float, on_time_rate: float, first_attempt_rate: float}>
     */
    private function getSlasTrend(
        ?Customer $customer,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): array {
        $results = [];
        $current = \DateTimeImmutable::createFromInterface($from);
        $end = \DateTimeImmutable::createFromInterface($to);

        // Determine interval: if range > 60 days, use monthly; otherwise weekly
        $diffDays = (int) $current->diff($end)->days;
        $useMonthly = $diffDays > 60;

        while ($current <= $end) {
            if ($useMonthly) {
                $periodStart = $current->modify('first day of this month')->setTime(0, 0, 0);
                $periodEnd = $current->modify('last day of this month')->setTime(23, 59, 59);
                $label = $periodStart->format('M Y');
                $next = $current->modify('+1 month');
            } else {
                $periodStart = $current->setTime(0, 0, 0);
                $periodEnd = $current->modify('+6 days')->setTime(23, 59, 59);
                $label = $periodStart->format('d M');
                $next = $current->modify('+7 days');
            }

            if ($periodEnd > $end) {
                $periodEnd = \DateTimeImmutable::createFromInterface($end)->setTime(23, 59, 59);
            }

            $conn = $this->em->getConnection();
            $sql = <<<'SQL'
                SELECT
                    COUNT(*) FILTER (WHERE rs.status IN ('DELIVERED', 'EXCEPTION')) AS total_completed,
                    COUNT(*) FILTER (WHERE rs.status = 'DELIVERED') AS total_delivered,
                    COUNT(*) FILTER (
                        WHERE rs.status = 'DELIVERED'
                        AND (rs.delivery_window_end IS NULL OR rs.delivered_at::time <= rs.delivery_window_end)
                    ) AS on_time_delivered
                FROM route_stop rs
                JOIN route_plan r ON rs.route_id = r.id
                WHERE rs.is_origin = false
                  AND r.start_at >= :from_date
                  AND r.start_at <= :to_date
                  AND r.deleted_at IS NULL
            SQL;

            $params = [
                'from_date' => $periodStart->format('Y-m-d H:i:s'),
                'to_date' => $periodEnd->format('Y-m-d H:i:s'),
            ];

            if ($customer !== null) {
                $sql .= ' AND r.customer_id = :customer_id';
                $params['customer_id'] = $customer->getId();
            }

            $row = $conn->fetchAssociative($sql, $params);

            $totalCompleted = (int) ($row['total_completed'] ?? 0);
            $totalDelivered = (int) ($row['total_delivered'] ?? 0);
            $onTimeDelivered = (int) ($row['on_time_delivered'] ?? 0);

            $otifRate = $totalDelivered > 0 ? round(($onTimeDelivered / $totalDelivered) * 100, 1) : 0.0;
            $onTimeRate = $totalCompleted > 0 ? round(($onTimeDelivered / $totalCompleted) * 100, 1) : 0.0;
            $firstAttemptRate = $totalCompleted > 0 ? round(($totalDelivered / $totalCompleted) * 100, 1) : 0.0;

            $results[] = [
                'period_label' => $label,
                'otif_rate' => $otifRate,
                'on_time_rate' => $onTimeRate,
                'first_attempt_rate' => $firstAttemptRate,
            ];

            $current = $next;
        }

        return $results;
    }

    /**
     * Driver ranking with SLA-relevant metrics.
     *
     * @return list<array{driver_name: string, driver_email: string, deliveries: int, exceptions: int, success_rate: float, avg_stops_per_hour: float}>
     */
    private function getDriverSlaRanking(
        ?Customer $customer,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): array {
        $conn = $this->em->getConnection();

        $sql = <<<'SQL'
            SELECT
                COALESCE(u.name, u.email) AS driver_name,
                u.email AS driver_email,
                COUNT(*) FILTER (WHERE rs.status = 'DELIVERED') AS deliveries,
                COUNT(*) FILTER (WHERE rs.status = 'EXCEPTION') AS exceptions,
                COUNT(*) FILTER (WHERE rs.status IN ('DELIVERED', 'EXCEPTION')) AS total_completed
            FROM route_stop rs
            JOIN route_plan r ON rs.route_id = r.id
            JOIN "user_account" u ON r.driver_id = u.id
            WHERE rs.is_origin = false
              AND r.start_at >= :from_date
              AND r.start_at <= :to_date
              AND r.deleted_at IS NULL
        SQL;

        $params = ['from_date' => $from->format('Y-m-d H:i:s'), 'to_date' => $to->format('Y-m-d H:i:s')];

        if ($customer !== null) {
            $sql .= ' AND r.customer_id = :customer_id';
            $params['customer_id'] = $customer->getId();
        }

        $sql .= ' GROUP BY u.id, u.name, u.email ORDER BY deliveries DESC';

        $rows = $conn->fetchAllAssociative($sql, $params);

        // Calculate avg stops per hour per driver
        $driverHoursSql = <<<'SQL'
            SELECT
                r.driver_id,
                COUNT(*) FILTER (WHERE rs.status = 'DELIVERED') AS delivered,
                SUM(EXTRACT(EPOCH FROM (r.end_at - r.start_at)) / 3600.0) AS total_hours
            FROM route_plan r
            JOIN route_stop rs ON rs.route_id = r.id AND rs.is_origin = false
            WHERE r.status = 'DONE'
              AND r.start_at IS NOT NULL
              AND r.end_at IS NOT NULL
              AND r.start_at >= :from_date
              AND r.start_at <= :to_date
              AND r.deleted_at IS NULL
        SQL;

        $hourParams = ['from_date' => $from->format('Y-m-d H:i:s'), 'to_date' => $to->format('Y-m-d H:i:s')];

        if ($customer !== null) {
            $driverHoursSql .= ' AND r.customer_id = :customer_id';
            $hourParams['customer_id'] = $customer->getId();
        }

        $driverHoursSql .= ' GROUP BY r.driver_id';

        $hoursRows = $conn->fetchAllAssociative($driverHoursSql, $hourParams);
        $hoursMap = [];
        foreach ($hoursRows as $hr) {
            $totalHours = (float) ($hr['total_hours'] ?? 0);
            $delivered = (int) ($hr['delivered'] ?? 0);
            $hoursMap[(int) $hr['driver_id']] = $totalHours > 0 ? round($delivered / $totalHours, 1) : 0.0;
        }

        // We need driver_id to map hours. Get it from a separate query or adapt.
        // Re-query with driver_id included
        $sqlWithId = str_replace(
            'COALESCE(u.name, u.email) AS driver_name,',
            'u.id AS driver_id, COALESCE(u.name, u.email) AS driver_name,',
            $sql,
        );

        $rowsWithId = $conn->fetchAllAssociative($sqlWithId, $params);

        return array_map(function (array $row) use ($hoursMap) {
            $deliveries = (int) $row['deliveries'];
            $exceptions = (int) $row['exceptions'];
            $totalCompleted = (int) $row['total_completed'];
            $driverId = (int) $row['driver_id'];

            return [
                'driver_name' => (string) $row['driver_name'],
                'driver_email' => (string) $row['driver_email'],
                'deliveries' => $deliveries,
                'exceptions' => $exceptions,
                'success_rate' => $totalCompleted > 0 ? round(($deliveries / $totalCompleted) * 100, 1) : 0.0,
                'avg_stops_per_hour' => $hoursMap[$driverId] ?? 0.0,
            ];
        }, $rowsWithId);
    }
}
