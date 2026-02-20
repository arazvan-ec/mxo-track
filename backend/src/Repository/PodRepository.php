<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Pod;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/** @extends ServiceEntityRepository<Pod> */
final class PodRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pod::class);
    }

    public function findOneByPublicId(string $publicId): ?Pod
    {
        try {
            return $this->findOneBy(['publicId' => Ulid::fromString($publicId)]);
        } catch (\Throwable) {
            return null;
        }
    }
}
