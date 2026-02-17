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
}
