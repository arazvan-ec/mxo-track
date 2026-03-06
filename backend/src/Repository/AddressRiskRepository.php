<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AddressRisk;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AddressRisk> */
final class AddressRiskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AddressRisk::class);
    }

    public function findByAddressHash(string $hash): ?AddressRisk
    {
        return $this->findOneBy(['addressHash' => $hash]);
    }
}
