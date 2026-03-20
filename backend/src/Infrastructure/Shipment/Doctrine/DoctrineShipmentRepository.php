<?php

declare(strict_types=1);

namespace App\Infrastructure\Shipment\Doctrine;

use App\Domain\Shipment\Repository\ShipmentRepositoryInterface;
use App\Entity\Shipment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineShipmentRepository implements ShipmentRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function findOneByPublicId(string $publicId): ?Shipment
    {
        try {
            return $this->em->getRepository(Shipment::class)
                ->findOneBy(['publicId' => Ulid::fromString($publicId)]);
        } catch (\Throwable) {
            return null;
        }
    }

    public function findOneByTrackingToken(string $trackingToken): ?Shipment
    {
        return $this->em->getRepository(Shipment::class)
            ->findOneBy(['trackingToken' => $trackingToken]);
    }

    public function save(Shipment $shipment): void
    {
        $this->em->persist($shipment);
    }

    public function remove(Shipment $shipment): void
    {
        $this->em->remove($shipment);
    }

    public function flush(): void
    {
        $this->em->flush();
    }
}
