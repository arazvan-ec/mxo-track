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

    public function findOneByTrackingToken(string $trackingToken): ?Shipment
    {
        return $this->findOneBy(['trackingToken' => $trackingToken]);
    }

    /** @return Shipment[] */
    public function findForTomorrow(): array
    {
        $tomorrow = new \DateTimeImmutable('tomorrow');
        $dayAfter = new \DateTimeImmutable('+2 days midnight');

        return $this->createQueryBuilder('s')
            ->where('s.estimatedDeliveryDate >= :from')
            ->andWhere('s.estimatedDeliveryDate < :to')
            ->andWhere('s.recipientPhone IS NOT NULL')
            ->setParameter('from', $tomorrow)
            ->setParameter('to', $dayAfter)
            ->getQuery()
            ->getResult();
    }

    /** @return Shipment[] */
    public function findWithEstimatedDeliveryWithinMinutes(int $minutes): array
    {
        $now = new \DateTimeImmutable();
        $until = new \DateTimeImmutable(sprintf('+%d minutes', $minutes));

        return $this->createQueryBuilder('s')
            ->where('s.estimatedDeliveryDate >= :now')
            ->andWhere('s.estimatedDeliveryDate <= :until')
            ->andWhere('s.recipientPhone IS NOT NULL')
            ->setParameter('now', $now)
            ->setParameter('until', $until)
            ->getQuery()
            ->getResult();
    }
}
