<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Customer;
use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteStop;
use App\Entity\Shipment;
use App\Entity\ShipmentEvent;
use App\Entity\User;
use App\Enum\RouteStatus;
use App\Enum\RouteStopStatus;
use App\Enum\ShipmentEventType;
use Doctrine\ORM\EntityManagerInterface;

final class ReportingService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * @return array{
     *     total_deliveries: int,
     *     total_exceptions: int,
     *     success_rate: float,
     *     avg_deliveries_per_route: float,
     *     by_driver: list<array{driver_id: int, driver_name: string, driver_email: string, deliveries: int, exceptions: int, routes: int}>,
     *     by_customer: list<array{customer_id: int, customer_name: string, deliveries: int, exceptions: int, routes: int}>
     * }
     */
    public function getDeliveryReport(
        ?\DateTimeInterface $from = null,
        ?\DateTimeInterface $to = null,
        ?Customer $customer = null,
        ?User $driver = null,
    ): array {
        // Build base query for route stops with date filtering via route
        $qb = $this->em->createQueryBuilder()
            ->select(
                'SUM(CASE WHEN rs.status = :delivered THEN 1 ELSE 0 END) as total_deliveries',
                'SUM(CASE WHEN rs.status = :exception THEN 1 ELSE 0 END) as total_exceptions',
            )
            ->from(RouteStop::class, 'rs')
            ->join('rs.route', 'r')
            ->where('rs.isOrigin = false')
            ->setParameter('delivered', RouteStopStatus::DELIVERED)
            ->setParameter('exception', RouteStopStatus::EXCEPTION);

        $this->applyDateFilters($qb, $from, $to);
        $this->applyEntityFilters($qb, $customer, $driver);

        $result = $qb->getQuery()->getSingleResult();
        $totalDeliveries = (int) ($result['total_deliveries'] ?? 0);
        $totalExceptions = (int) ($result['total_exceptions'] ?? 0);
        $total = $totalDeliveries + $totalExceptions;
        $successRate = $total > 0 ? round(($totalDeliveries / $total) * 100, 1) : 0.0;

        // Routes completed count
        $routesQb = $this->em->createQueryBuilder()
            ->select('COUNT(DISTINCT r.id)')
            ->from(Route::class, 'r')
            ->where('r.status = :done')
            ->setParameter('done', RouteStatus::DONE);

        if ($from !== null) {
            $routesQb->andWhere('r.endAt >= :from')->setParameter('from', $from);
        }
        if ($to !== null) {
            $routesQb->andWhere('r.endAt <= :to')->setParameter('to', $to);
        }
        if ($customer !== null) {
            $routesQb->andWhere('r.customer = :customer')->setParameter('customer', $customer);
        }
        if ($driver !== null) {
            $routesQb->andWhere('r.driver = :driver')->setParameter('driver', $driver);
        }

        $routesCompleted = (int) $routesQb->getQuery()->getSingleScalarResult();
        $avgDeliveriesPerRoute = $routesCompleted > 0
            ? round($totalDeliveries / $routesCompleted, 1)
            : 0.0;

        // By driver breakdown
        $byDriverQb = $this->em->createQueryBuilder()
            ->select(
                'd.id as driver_id',
                'COALESCE(d.name, d.email) as driver_name',
                'd.email as driver_email',
                'SUM(CASE WHEN rs.status = :delivered THEN 1 ELSE 0 END) as deliveries',
                'SUM(CASE WHEN rs.status = :exception THEN 1 ELSE 0 END) as exceptions',
                'COUNT(DISTINCT r.id) as routes',
            )
            ->from(RouteStop::class, 'rs')
            ->join('rs.route', 'r')
            ->join('r.driver', 'd')
            ->where('rs.isOrigin = false')
            ->setParameter('delivered', RouteStopStatus::DELIVERED)
            ->setParameter('exception', RouteStopStatus::EXCEPTION)
            ->groupBy('d.id, d.name, d.email')
            ->orderBy('deliveries', 'DESC');

        $this->applyDateFilters($byDriverQb, $from, $to);
        if ($customer !== null) {
            $byDriverQb->andWhere('r.customer = :customer')->setParameter('customer', $customer);
        }
        if ($driver !== null) {
            $byDriverQb->andWhere('r.driver = :driver')->setParameter('driver', $driver);
        }

        $byDriver = array_map(fn(array $row) => [
            'driver_id' => (int) $row['driver_id'],
            'driver_name' => (string) $row['driver_name'],
            'driver_email' => (string) $row['driver_email'],
            'deliveries' => (int) $row['deliveries'],
            'exceptions' => (int) $row['exceptions'],
            'routes' => (int) $row['routes'],
        ], $byDriverQb->getQuery()->getResult());

        // By customer breakdown
        $byCustomerQb = $this->em->createQueryBuilder()
            ->select(
                'c.id as customer_id',
                'c.name as customer_name',
                'SUM(CASE WHEN rs.status = :delivered THEN 1 ELSE 0 END) as deliveries',
                'SUM(CASE WHEN rs.status = :exception THEN 1 ELSE 0 END) as exceptions',
                'COUNT(DISTINCT r.id) as routes',
            )
            ->from(RouteStop::class, 'rs')
            ->join('rs.route', 'r')
            ->join('r.customer', 'c')
            ->where('rs.isOrigin = false')
            ->setParameter('delivered', RouteStopStatus::DELIVERED)
            ->setParameter('exception', RouteStopStatus::EXCEPTION)
            ->groupBy('c.id, c.name')
            ->orderBy('deliveries', 'DESC');

        $this->applyDateFilters($byCustomerQb, $from, $to);
        if ($customer !== null) {
            $byCustomerQb->andWhere('r.customer = :customer')->setParameter('customer', $customer);
        }
        if ($driver !== null) {
            $byCustomerQb->andWhere('r.driver = :driver')->setParameter('driver', $driver);
        }

        $byCustomer = array_map(fn(array $row) => [
            'customer_id' => (int) $row['customer_id'],
            'customer_name' => (string) $row['customer_name'],
            'deliveries' => (int) $row['deliveries'],
            'exceptions' => (int) $row['exceptions'],
            'routes' => (int) $row['routes'],
        ], $byCustomerQb->getQuery()->getResult());

        return [
            'total_deliveries' => $totalDeliveries,
            'total_exceptions' => $totalExceptions,
            'success_rate' => $successRate,
            'avg_deliveries_per_route' => $avgDeliveriesPerRoute,
            'by_driver' => $byDriver,
            'by_customer' => $byCustomer,
        ];
    }

    /**
     * @return array{
     *     routes_completed: int,
     *     stops_delivered: int,
     *     stops_exception: int,
     *     avg_stops_per_hour: float,
     *     exception_rate: float
     * }
     */
    public function getDriverPerformance(
        User $driver,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): array {
        $routesCompleted = (int) $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Route::class, 'r')
            ->where('r.driver = :driver')
            ->andWhere('r.status = :done')
            ->andWhere('r.endAt >= :from')
            ->andWhere('r.endAt <= :to')
            ->setParameter('driver', $driver)
            ->setParameter('done', RouteStatus::DONE)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        $stopsQb = $this->em->createQueryBuilder()
            ->select(
                'SUM(CASE WHEN rs.status = :delivered THEN 1 ELSE 0 END) as stops_delivered',
                'SUM(CASE WHEN rs.status = :exception THEN 1 ELSE 0 END) as stops_exception',
            )
            ->from(RouteStop::class, 'rs')
            ->join('rs.route', 'r')
            ->where('r.driver = :driver')
            ->andWhere('rs.isOrigin = false')
            ->andWhere('r.startAt >= :from')
            ->andWhere('r.startAt <= :to')
            ->setParameter('driver', $driver)
            ->setParameter('delivered', RouteStopStatus::DELIVERED)
            ->setParameter('exception', RouteStopStatus::EXCEPTION)
            ->setParameter('from', $from)
            ->setParameter('to', $to);

        $stopsResult = $stopsQb->getQuery()->getSingleResult();
        $stopsDelivered = (int) ($stopsResult['stops_delivered'] ?? 0);
        $stopsException = (int) ($stopsResult['stops_exception'] ?? 0);
        $totalStops = $stopsDelivered + $stopsException;
        $exceptionRate = $totalStops > 0 ? round(($stopsException / $totalStops) * 100, 1) : 0.0;

        // Calculate average stops per hour based on route durations
        $routeDurations = $this->em->createQueryBuilder()
            ->select('r.startAt', 'r.endAt')
            ->from(Route::class, 'r')
            ->where('r.driver = :driver')
            ->andWhere('r.status = :done')
            ->andWhere('r.startAt IS NOT NULL')
            ->andWhere('r.endAt IS NOT NULL')
            ->andWhere('r.endAt >= :from')
            ->andWhere('r.endAt <= :to')
            ->setParameter('driver', $driver)
            ->setParameter('done', RouteStatus::DONE)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        $totalHours = 0.0;
        foreach ($routeDurations as $route) {
            if ($route['startAt'] instanceof \DateTimeInterface && $route['endAt'] instanceof \DateTimeInterface) {
                $diff = $route['endAt']->getTimestamp() - $route['startAt']->getTimestamp();
                $totalHours += max($diff, 0) / 3600.0;
            }
        }

        $avgStopsPerHour = $totalHours > 0 ? round($stopsDelivered / $totalHours, 1) : 0.0;

        return [
            'routes_completed' => $routesCompleted,
            'stops_delivered' => $stopsDelivered,
            'stops_exception' => $stopsException,
            'avg_stops_per_hour' => $avgStopsPerHour,
            'exception_rate' => $exceptionRate,
        ];
    }

    /**
     * @return array{
     *     total_shipments: int,
     *     delivered: int,
     *     exceptions: int,
     *     pending: int,
     *     completion_rate: float
     * }
     */
    public function getCustomerReport(
        Customer $customer,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): array {
        // Total shipments for this customer in the period
        $totalShipments = (int) $this->em->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(Shipment::class, 's')
            ->where('s.customer = :customer')
            ->andWhere('s.createdAt >= :from')
            ->andWhere('s.createdAt <= :to')
            ->setParameter('customer', $customer)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        // Stop-based metrics for this customer's routes
        $stopsQb = $this->em->createQueryBuilder()
            ->select(
                'SUM(CASE WHEN rs.status = :delivered THEN 1 ELSE 0 END) as delivered',
                'SUM(CASE WHEN rs.status = :exception THEN 1 ELSE 0 END) as exceptions',
                'SUM(CASE WHEN rs.status = :pending THEN 1 ELSE 0 END) as pending',
            )
            ->from(RouteStop::class, 'rs')
            ->join('rs.route', 'r')
            ->where('r.customer = :customer')
            ->andWhere('rs.isOrigin = false')
            ->andWhere('r.startAt >= :from OR r.startAt IS NULL')
            ->setParameter('customer', $customer)
            ->setParameter('delivered', RouteStopStatus::DELIVERED)
            ->setParameter('exception', RouteStopStatus::EXCEPTION)
            ->setParameter('pending', RouteStopStatus::PENDING)
            ->setParameter('from', $from);

        $stopsResult = $stopsQb->getQuery()->getSingleResult();
        $delivered = (int) ($stopsResult['delivered'] ?? 0);
        $exceptions = (int) ($stopsResult['exceptions'] ?? 0);
        $pending = (int) ($stopsResult['pending'] ?? 0);
        $totalHandled = $delivered + $exceptions + $pending;
        $completionRate = $totalHandled > 0 ? round(($delivered / $totalHandled) * 100, 1) : 0.0;

        return [
            'total_shipments' => $totalShipments,
            'delivered' => $delivered,
            'exceptions' => $exceptions,
            'pending' => $pending,
            'completion_rate' => $completionRate,
        ];
    }

    /**
     * @return list<array{period_label: string, deliveries: int, exceptions: int, routes_completed: int}>
     */
    public function getTrendData(string $period = 'week', int $periods = 12): array {
        $results = [];
        $now = new \DateTimeImmutable();

        for ($i = $periods - 1; $i >= 0; $i--) {
            if ($period === 'month') {
                $start = $now->modify("-{$i} months")->modify('first day of this month')->setTime(0, 0, 0);
                $end = $start->modify('last day of this month')->setTime(23, 59, 59);
                $label = $start->format('M Y');
            } else {
                // week
                $start = $now->modify("-{$i} weeks")->modify('monday this week')->setTime(0, 0, 0);
                $end = $start->modify('+6 days')->setTime(23, 59, 59);
                $label = $start->format('d M');
            }

            // Deliveries and exceptions in this period
            $stopsQb = $this->em->createQueryBuilder()
                ->select(
                    'SUM(CASE WHEN rs.status = :delivered THEN 1 ELSE 0 END) as deliveries',
                    'SUM(CASE WHEN rs.status = :exception THEN 1 ELSE 0 END) as exceptions',
                )
                ->from(RouteStop::class, 'rs')
                ->join('rs.route', 'r')
                ->where('rs.isOrigin = false')
                ->andWhere('rs.deliveredAt >= :start AND rs.deliveredAt <= :end')
                ->setParameter('delivered', RouteStopStatus::DELIVERED)
                ->setParameter('exception', RouteStopStatus::EXCEPTION)
                ->setParameter('start', $start)
                ->setParameter('end', $end);

            $stopsResult = $stopsQb->getQuery()->getSingleResult();

            // Routes completed in this period
            $routesCompleted = (int) $this->em->createQueryBuilder()
                ->select('COUNT(r.id)')
                ->from(Route::class, 'r')
                ->where('r.status = :done')
                ->andWhere('r.endAt >= :start AND r.endAt <= :end')
                ->setParameter('done', RouteStatus::DONE)
                ->setParameter('start', $start)
                ->setParameter('end', $end)
                ->getQuery()
                ->getSingleScalarResult();

            $results[] = [
                'period_label' => $label,
                'deliveries' => (int) ($stopsResult['deliveries'] ?? 0),
                'exceptions' => (int) ($stopsResult['exceptions'] ?? 0),
                'routes_completed' => $routesCompleted,
            ];
        }

        return $results;
    }

    /**
     * Get daily delivery counts for the last N days (used for dashboard mini chart).
     *
     * @return list<array{date: string, deliveries: int}>
     */
    public function getDailyDeliveries(int $days = 7): array
    {
        $results = [];
        $now = new \DateTimeImmutable();

        for ($i = $days - 1; $i >= 0; $i--) {
            $dayStart = $now->modify("-{$i} days")->setTime(0, 0, 0);
            $dayEnd = $dayStart->setTime(23, 59, 59);

            $count = (int) $this->em->createQueryBuilder()
                ->select('COUNT(rs.id)')
                ->from(RouteStop::class, 'rs')
                ->where('rs.status = :delivered')
                ->andWhere('rs.deliveredAt >= :start')
                ->andWhere('rs.deliveredAt <= :end')
                ->setParameter('delivered', RouteStopStatus::DELIVERED)
                ->setParameter('start', $dayStart)
                ->setParameter('end', $dayEnd)
                ->getQuery()
                ->getSingleScalarResult();

            $results[] = [
                'date' => $dayStart->format('D d'),
                'deliveries' => $count,
            ];
        }

        return $results;
    }

    /**
     * Get top drivers by deliveries count within a date range.
     *
     * @return list<array{driver_name: string, driver_email: string, deliveries: int}>
     */
    public function getTopDrivers(int $limit = 5, ?int $days = 7): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select(
                'COALESCE(d.name, d.email) as driver_name',
                'd.email as driver_email',
                'COUNT(rs.id) as deliveries',
            )
            ->from(RouteStop::class, 'rs')
            ->join('rs.route', 'r')
            ->join('r.driver', 'd')
            ->where('rs.status = :delivered')
            ->andWhere('rs.isOrigin = false')
            ->setParameter('delivered', RouteStopStatus::DELIVERED)
            ->groupBy('d.id, d.name, d.email')
            ->orderBy('deliveries', 'DESC')
            ->setMaxResults($limit);

        if ($days !== null) {
            $from = (new \DateTimeImmutable())->modify("-{$days} days")->setTime(0, 0, 0);
            $qb->andWhere('rs.deliveredAt >= :from')->setParameter('from', $from);
        }

        return array_map(fn(array $row) => [
            'driver_name' => (string) $row['driver_name'],
            'driver_email' => (string) $row['driver_email'],
            'deliveries' => (int) $row['deliveries'],
        ], $qb->getQuery()->getResult());
    }

    /**
     * Get all drivers with their performance for ranking purposes.
     *
     * @return list<array{driver_id: int, driver_name: string, driver_email: string, routes_completed: int, deliveries: int, exceptions: int, success_rate: float}>
     */
    public function getDriverRanking(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select(
                'd.id as driver_id',
                'COALESCE(d.name, d.email) as driver_name',
                'd.email as driver_email',
                'SUM(CASE WHEN rs.status = :delivered THEN 1 ELSE 0 END) as deliveries',
                'SUM(CASE WHEN rs.status = :exception THEN 1 ELSE 0 END) as exceptions',
                'COUNT(DISTINCT r.id) as routes_completed',
            )
            ->from(RouteStop::class, 'rs')
            ->join('rs.route', 'r')
            ->join('r.driver', 'd')
            ->where('rs.isOrigin = false')
            ->andWhere('r.startAt >= :from')
            ->andWhere('r.startAt <= :to')
            ->setParameter('delivered', RouteStopStatus::DELIVERED)
            ->setParameter('exception', RouteStopStatus::EXCEPTION)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('d.id, d.name, d.email')
            ->orderBy('deliveries', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(function (array $row) {
            $deliveries = (int) $row['deliveries'];
            $exceptions = (int) $row['exceptions'];
            $total = $deliveries + $exceptions;

            return [
                'driver_id' => (int) $row['driver_id'],
                'driver_name' => (string) $row['driver_name'],
                'driver_email' => (string) $row['driver_email'],
                'routes_completed' => (int) $row['routes_completed'],
                'deliveries' => $deliveries,
                'exceptions' => $exceptions,
                'success_rate' => $total > 0 ? round(($deliveries / $total) * 100, 1) : 0.0,
            ];
        }, $rows);
    }

    /**
     * Get stop status distribution for a pie chart.
     *
     * @return array{delivered: int, exception: int, pending: int, skipped: int}
     */
    public function getStopStatusDistribution(
        ?\DateTimeInterface $from = null,
        ?\DateTimeInterface $to = null,
    ): array {
        $qb = $this->em->createQueryBuilder()
            ->select(
                'SUM(CASE WHEN rs.status = :delivered THEN 1 ELSE 0 END) as delivered',
                'SUM(CASE WHEN rs.status = :exception THEN 1 ELSE 0 END) as exception',
                'SUM(CASE WHEN rs.status = :pending THEN 1 ELSE 0 END) as pending',
                'SUM(CASE WHEN rs.status = :skipped THEN 1 ELSE 0 END) as skipped',
            )
            ->from(RouteStop::class, 'rs')
            ->join('rs.route', 'r')
            ->where('rs.isOrigin = false')
            ->setParameter('delivered', RouteStopStatus::DELIVERED)
            ->setParameter('exception', RouteStopStatus::EXCEPTION)
            ->setParameter('pending', RouteStopStatus::PENDING)
            ->setParameter('skipped', RouteStopStatus::SKIPPED);

        if ($from !== null) {
            $qb->andWhere('r.startAt >= :from')->setParameter('from', $from);
        }
        if ($to !== null) {
            $qb->andWhere('r.startAt <= :to')->setParameter('to', $to);
        }

        $result = $qb->getQuery()->getSingleResult();

        return [
            'delivered' => (int) ($result['delivered'] ?? 0),
            'exception' => (int) ($result['exception'] ?? 0),
            'pending' => (int) ($result['pending'] ?? 0),
            'skipped' => (int) ($result['skipped'] ?? 0),
        ];
    }

    private function applyDateFilters(
        \Doctrine\ORM\QueryBuilder $qb,
        ?\DateTimeInterface $from,
        ?\DateTimeInterface $to,
    ): void {
        if ($from !== null) {
            $qb->andWhere('r.startAt >= :from')->setParameter('from', $from);
        }
        if ($to !== null) {
            $qb->andWhere('r.startAt <= :to')->setParameter('to', $to);
        }
    }

    private function applyEntityFilters(
        \Doctrine\ORM\QueryBuilder $qb,
        ?Customer $customer,
        ?User $driver,
    ): void {
        if ($customer !== null) {
            $qb->andWhere('r.customer = :customer')->setParameter('customer', $customer);
        }
        if ($driver !== null) {
            $qb->andWhere('r.driver = :driver')->setParameter('driver', $driver);
        }
    }
}
