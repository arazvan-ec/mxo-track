<?php

declare(strict_types=1);

namespace App\Infrastructure\Shipment\Doctrine;

use App\Domain\Shipment\Repository\PodRepositoryInterface;
use App\Domain\Shipment\Model\Pod;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrinePodRepository implements PodRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function findOneByPublicId(string $publicId): ?Pod
    {
        try {
            return $this->em->getRepository(Pod::class)
                ->findOneBy(['publicId' => Ulid::fromString($publicId)]);
        } catch (\Throwable) {
            return null;
        }
    }

    public function save(Pod $pod): void
    {
        $this->em->persist($pod);
    }

    public function flush(): void
    {
        $this->em->flush();
    }
}
