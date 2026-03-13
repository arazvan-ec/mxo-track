<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Route;
use App\Entity\RouteSnapshot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RouteSnapshot>
 */
class RouteSnapshotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RouteSnapshot::class);
    }

    public function findByRoute(Route $route): ?RouteSnapshot
    {
        return $this->findOneBy(['route' => $route]);
    }
}
