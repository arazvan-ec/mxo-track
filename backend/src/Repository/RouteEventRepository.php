<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Route;
use App\Entity\RouteEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RouteEvent>
 */
final class RouteEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RouteEvent::class);
    }

    /**
     * @return RouteEvent[]
     */
    public function findByRoute(Route $route): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.route = :route')
            ->setParameter('route', $route)
            ->orderBy('e.occurredAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
