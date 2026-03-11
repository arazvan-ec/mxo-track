<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\VehicleLastPosition;
use App\Enum\RouteStatus;
use App\Enum\RouteStopStatus;
use Doctrine\ORM\EntityManagerInterface;

final class OperatorKpiService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
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
        $todayStart = new \DateTimeImmutable('today midnight');
        $sevenDaysAgo = new \DateTimeImmutable('-7 days midnight');

        return [
            'activeRoutes' => $this->countActiveRoutes(),
            'deliveriesToday' => $this->countDeliveriesToday($todayStart),
            'exceptionsToday' => $this->countExceptionsToday(),
            'completionRate' => $this->calculateCompletionRate(),
            'successRate7d' => $this->calculateSuccessRate7d($sevenDaysAgo),
            'vehiclesWithPosition' => $this->countVehiclesWithPosition(),
            'topDrivers' => $this->getTopDrivers($sevenDaysAgo),
        ];
    }

    private function countActiveRoutes(): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Route::class, 'r')
            ->where('r.status IN (:statuses)')
            ->setParameter('statuses', [RouteStatus::ACTIVE, RouteStatus::PLANNED])
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countDeliveriesToday(\DateTimeImmutable $todayStart): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(rs.id)')
            ->from(RouteStop::class, 'rs')
            ->where('rs.status = :status')
            ->andWhere('rs.deliveredAt >= :today')
            ->setParameter('status', RouteStopStatus::DELIVERED)
            ->setParameter('today', $todayStart)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countExceptionsToday(): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(rs.id)')
            ->from(RouteStop::class, 'rs')
            ->join('rs.route', 'r')
            ->where('rs.status = :status')
            ->andWhere('r.status IN (:routeStatuses)')
            ->setParameter('status', RouteStopStatus::EXCEPTION)
            ->setParameter('routeStatuses', [RouteStatus::ACTIVE, RouteStatus::PLANNED, RouteStatus::DONE])
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function calculateCompletionRate(): float
    {
        $rows = $this->em->createQueryBuilder()
            ->select(
                'IDENTITY(rs.route) AS routeId',
                'COUNT(rs.id) AS total',
                'SUM(CASE WHEN rs.status = :delivered THEN 1 ELSE 0 END) AS delivered',
            )
            ->from(RouteStop::class, 'rs')
            ->join('rs.route', 'r')
            ->where('r.status IN (:statuses)')
            ->andWhere('rs.isOrigin = false')
            ->setParameter('delivered', RouteStopStatus::DELIVERED)
            ->setParameter('statuses', [RouteStatus::ACTIVE, RouteStatus::PLANNED])
            ->groupBy('rs.route')
            ->getQuery()
            ->getArrayResult();

        $totalStops = 0;
        $totalDelivered = 0;
        foreach ($rows as $row) {
            $totalStops += (int) $row['total'];
            $totalDelivered += (int) $row['delivered'];
        }

        return $totalStops > 0 ? round(($totalDelivered / $totalStops) * 100, 1) : 0.0;
    }

    private function calculateSuccessRate7d(\DateTimeImmutable $sevenDaysAgo): float
    {
        $delivered = (int) $this->em->createQueryBuilder()
            ->select('COUNT(rs.id)')
            ->from(RouteStop::class, 'rs')
            ->join('rs.route', 'r')
            ->where('rs.status = :status')
            ->andWhere('rs.isOrigin = false')
            ->andWhere('r.startAt >= :since')
            ->setParameter('status', RouteStopStatus::DELIVERED)
            ->setParameter('since', $sevenDaysAgo)
            ->getQuery()
            ->getSingleScalarResult();

        $exceptions = (int) $this->em->createQueryBuilder()
            ->select('COUNT(rs.id)')
            ->from(RouteStop::class, 'rs')
            ->join('rs.route', 'r')
            ->where('rs.status = :status')
            ->andWhere('rs.isOrigin = false')
            ->andWhere('r.startAt >= :since')
            ->setParameter('status', RouteStopStatus::EXCEPTION)
            ->setParameter('since', $sevenDaysAgo)
            ->getQuery()
            ->getSingleScalarResult();

        $total = $delivered + $exceptions;

        return $total > 0 ? round(($delivered / $total) * 100, 1) : 0.0;
    }

    private function countVehiclesWithPosition(): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(vlp.id)')
            ->from(VehicleLastPosition::class, 'vlp')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Top 3 drivers by deliveries in last 7 days.
     *
     * Uses DBAL for PostgreSQL-specific FILTER (WHERE ...) and COALESCE on User fields,
     * which are not expressible in standard DQL. The soft_delete filter on Route is
     * applied manually via deleted_at IS NULL.
     *
     * @return list<array{driver_name: string, deliveries: int, exceptions: int, success_rate: float}>
     */
    private function getTopDrivers(\DateTimeImmutable $sevenDaysAgo): array
    {
        $rows = $this->em->getConnection()->fetchAllAssociative(
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
            ['since' => $sevenDaysAgo->format('Y-m-d H:i:s')],
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
