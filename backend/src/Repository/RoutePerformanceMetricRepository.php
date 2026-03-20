<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Customer;
use App\Domain\Route\Model\Route;
use App\Entity\RoutePerformanceMetric;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<RoutePerformanceMetric> */
final class RoutePerformanceMetricRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RoutePerformanceMetric::class);
    }

    public function findByRoute(Route $route): ?RoutePerformanceMetric
    {
        return $this->findOneBy(['route' => $route]);
    }

    /**
     * @return list<RoutePerformanceMetric>
     */
    public function findByCustomerSince(Customer $customer, \DateTimeImmutable $since): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.customer = :customer')
            ->andWhere('m.createdAt >= :since')
            ->setParameter('customer', $customer)
            ->setParameter('since', $since)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get aggregate metrics scoped to a specific customer.
     *
     * @return array{total_km_saved: ?string, total_time_saved_minutes: ?int, avg_delivery_success_rate: ?string, avg_savings_percent: ?string, routes_with_metrics: int}
     */
    public function getCustomerAggregateMetrics(Customer $customer, \DateTimeImmutable $since): array
    {
        $result = $this->createQueryBuilder('m')
            ->select(
                'SUM(m.kmSaved) as total_km_saved',
                'SUM(m.timeSavedMinutes) as total_time_saved_minutes',
                'AVG(m.deliverySuccessRate) as avg_delivery_success_rate',
                'AVG(m.planAccuracyPercent) as avg_savings_percent',
                'COUNT(m.id) as routes_with_metrics',
            )
            ->where('m.customer = :customer')
            ->andWhere('m.createdAt >= :since')
            ->setParameter('customer', $customer)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleResult();

        return [
            'total_km_saved' => $result['total_km_saved'],
            'total_time_saved_minutes' => $result['total_time_saved_minutes'] !== null ? (int) $result['total_time_saved_minutes'] : null,
            'avg_delivery_success_rate' => $result['avg_delivery_success_rate'],
            'avg_savings_percent' => $result['avg_savings_percent'],
            'routes_with_metrics' => (int) $result['routes_with_metrics'],
        ];
    }

    /**
     * Get aggregate metrics for a customer within a specific date range (for billing).
     *
     * @return array{total_km_saved: ?string, total_time_saved_minutes: ?int, avg_savings_percent: ?string, routes_with_metrics: int}
     */
    public function getCustomerPeriodAggregates(Customer $customer, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $result = $this->createQueryBuilder('m')
            ->select(
                'SUM(m.kmSaved) as total_km_saved',
                'SUM(m.timeSavedMinutes) as total_time_saved_minutes',
                'AVG(m.planAccuracyPercent) as avg_savings_percent',
                'COUNT(m.id) as routes_with_metrics',
            )
            ->where('m.customer = :customer')
            ->andWhere('m.createdAt >= :from')
            ->andWhere('m.createdAt <= :to')
            ->setParameter('customer', $customer)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleResult();

        return [
            'total_km_saved' => $result['total_km_saved'],
            'total_time_saved_minutes' => $result['total_time_saved_minutes'] !== null ? (int) $result['total_time_saved_minutes'] : null,
            'avg_savings_percent' => $result['avg_savings_percent'],
            'routes_with_metrics' => (int) $result['routes_with_metrics'],
        ];
    }

    /**
     * Get aggregate metrics for a period, optionally filtered by optimizer.
     *
     * @return array{avg_delivery_rate: ?string, avg_km_saved: ?string, total_routes: int, avg_plan_accuracy: ?string}
     */
    public function getAggregateMetrics(\DateTimeImmutable $since, ?string $optimizerUsed = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->select(
                'AVG(m.deliverySuccessRate) as avg_delivery_rate',
                'AVG(m.kmSaved) as avg_km_saved',
                'COUNT(m.id) as total_routes',
                'AVG(m.planAccuracyPercent) as avg_plan_accuracy',
            )
            ->where('m.createdAt >= :since')
            ->setParameter('since', $since);

        if ($optimizerUsed !== null) {
            $qb->andWhere('m.optimizerUsed = :optimizer')
                ->setParameter('optimizer', $optimizerUsed);
        }

        $result = $qb->getQuery()->getSingleResult();

        return [
            'avg_delivery_rate' => $result['avg_delivery_rate'],
            'avg_km_saved' => $result['avg_km_saved'],
            'total_routes' => (int) $result['total_routes'],
            'avg_plan_accuracy' => $result['avg_plan_accuracy'],
        ];
    }

    /**
     * Get metrics grouped by optimizer for comparison.
     *
     * @return list<array{optimizer_used: string, avg_delivery_rate: ?string, avg_km_saved: ?string, total_routes: int}>
     */
    public function getMetricsByOptimizer(\DateTimeImmutable $since): array
    {
        return $this->createQueryBuilder('m')
            ->select(
                'm.optimizerUsed as optimizer_used',
                'AVG(m.deliverySuccessRate) as avg_delivery_rate',
                'AVG(m.kmSaved) as avg_km_saved',
                'COUNT(m.id) as total_routes',
            )
            ->where('m.createdAt >= :since')
            ->setParameter('since', $since)
            ->groupBy('m.optimizerUsed')
            ->getQuery()
            ->getResult();
    }
}
