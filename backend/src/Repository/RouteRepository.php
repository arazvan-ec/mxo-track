<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Route;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/** @extends ServiceEntityRepository<Route> */
final class RouteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Route::class);
    }

    public function findOneByPublicId(string $publicId): ?Route
    {
        try {
            return $this->findOneBy(['publicId' => Ulid::fromString($publicId)]);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<string> Public IDs of active/planned routes for a customer
     */
    public function findActiveRoutePublicIdsForCustomer(\App\Entity\Customer $customer): array
    {
        $qb = $this->createQueryBuilder('r')
            ->select('CAST(r.publicId AS text)')
            ->where('r.customer = :customer')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('customer', $customer)
            ->setParameter('statuses', [\App\Enum\RouteStatus::ACTIVE, \App\Enum\RouteStatus::PLANNED]);

        return array_column($qb->getQuery()->getScalarResult(), 1);
    }

    /**
     * @return list<string> Public IDs of active routes assigned to a driver
     */
    public function findActiveRoutePublicIdsForDriver(\App\Entity\User $driver): array
    {
        $qb = $this->createQueryBuilder('r')
            ->select('CAST(r.publicId AS text)')
            ->where('r.driver = :driver')
            ->andWhere('r.status = :status')
            ->setParameter('driver', $driver)
            ->setParameter('status', \App\Enum\RouteStatus::ACTIVE);

        return array_column($qb->getQuery()->getScalarResult(), 1);
    }
}
