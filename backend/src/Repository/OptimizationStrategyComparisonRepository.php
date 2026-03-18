<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OptimizationStrategyComparison;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<OptimizationStrategyComparison> */
final class OptimizationStrategyComparisonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OptimizationStrategyComparison::class);
    }

    /**
     * @return list<OptimizationStrategyComparison>
     */
    public function findRecent(int $limit = 20): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<OptimizationStrategyComparison>
     */
    public function findSince(\DateTimeImmutable $since): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.createdAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find comparisons that have outcome data recorded.
     *
     * @return list<OptimizationStrategyComparison>
     */
    public function findWithOutcomes(\DateTimeImmutable $since): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.createdAt >= :since')
            ->andWhere('c.actualOutcome IS NOT NULL')
            ->setParameter('since', $since)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
