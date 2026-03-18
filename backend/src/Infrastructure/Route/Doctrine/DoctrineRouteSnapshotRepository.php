<?php

declare(strict_types=1);

namespace App\Infrastructure\Route\Doctrine;

use App\Domain\Route\Model\RouteSnapshot;
use App\Domain\Route\Repository\RouteSnapshotRepositoryInterface;
use App\Entity\Route;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RouteSnapshot>
 */
final class DoctrineRouteSnapshotRepository extends ServiceEntityRepository implements RouteSnapshotRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RouteSnapshot::class);
    }

    public function findByRoute(Route $route): ?RouteSnapshot
    {
        return $this->findOneBy(['route' => $route]);
    }

    public function findByRoutes(array $routes): array
    {
        if (\count($routes) === 0) {
            return [];
        }

        $snapshots = $this->createQueryBuilder('s')
            ->where('s.route IN (:routes)')
            ->setParameter('routes', $routes)
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($snapshots as $snapshot) {
            $map[$snapshot->getRoute()->getId()] = $snapshot;
        }

        return $map;
    }

    public function save(RouteSnapshot $snapshot): void
    {
        $this->getEntityManager()->persist($snapshot);
    }
}
