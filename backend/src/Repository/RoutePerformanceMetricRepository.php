<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Customer;
use App\Entity\Route;
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
