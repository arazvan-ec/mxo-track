<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Route;
use App\Entity\RouteOptimizationLog;
use App\Enum\OptimizationOperation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/** @extends ServiceEntityRepository<RouteOptimizationLog> */
final class RouteOptimizationLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RouteOptimizationLog::class);
    }

    public function findOneByPublicId(string $publicId): ?RouteOptimizationLog
    {
        try {
            return $this->findOneBy(['publicId' => Ulid::fromString($publicId)]);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return list<RouteOptimizationLog> */
    public function findByRoute(Route $route): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.route = :route')
            ->setParameter('route', $route)
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<RouteOptimizationLog> */
    public function findRecent(int $limit = 50): array
    {
        return $this->createQueryBuilder('l')
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return list<RouteOptimizationLog> */
    public function findByOperation(OptimizationOperation $operation, int $limit = 50): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.operation = :operation')
            ->setParameter('operation', $operation)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
