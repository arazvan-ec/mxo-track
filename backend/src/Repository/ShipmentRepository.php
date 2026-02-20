<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Shipment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/** @extends ServiceEntityRepository<Shipment> */
final class ShipmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Shipment::class);
    }

    public function findOneByPublicId(string $publicId): ?Shipment
    {
        try {
            return $this->findOneBy(['publicId' => Ulid::fromString($publicId)]);
        } catch (\Throwable) {
            return null;
        }
    }
}
