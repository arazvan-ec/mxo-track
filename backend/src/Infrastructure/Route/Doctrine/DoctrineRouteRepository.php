<?php

declare(strict_types=1);

namespace App\Infrastructure\Route\Doctrine;

use App\Domain\Route\Repository\RouteRepositoryInterface;
use App\Entity\Customer;
use App\Entity\Vehicle;
use App\Domain\Route\Model\Route;
use App\Entity\User;
use App\Enum\RouteStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineRouteRepository implements RouteRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function findOneByPublicId(string $publicId): ?Route
    {
        try {
            return $this->em->getRepository(Route::class)
                ->findOneBy(['publicId' => Ulid::fromString($publicId)]);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    public function findActiveRoutePublicIdsForCustomer(Customer $customer): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('CAST(r.publicId AS text) AS pid')
            ->from(Route::class, 'r')
            ->where('r.customer = :customer')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('customer', $customer)
            ->setParameter('statuses', [RouteStatus::ACTIVE, RouteStatus::PLANNED]);

        return array_column($qb->getQuery()->getScalarResult(), 'pid');
    }

    public function findActiveRoutePublicIdsForDriver(User $driver): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('CAST(r.publicId AS text) AS pid')
            ->from(Route::class, 'r')
            ->where('r.driver = :driver')
            ->andWhere('r.status = :status')
            ->setParameter('driver', $driver)
            ->setParameter('status', RouteStatus::ACTIVE);

        return array_column($qb->getQuery()->getScalarResult(), 'pid');
    }

    public function findLastByVehicle(Vehicle $vehicle): ?Route
    {
        return $this->em->createQueryBuilder()
            ->select('r')
            ->from(Route::class, 'r')
            ->where('r.vehicle = :vehicle')
            ->orderBy('r.createdAt', 'DESC')
            ->setParameter('vehicle', $vehicle)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function save(Route $route): void
    {
        $this->em->persist($route);
    }

    public function remove(Route $route): void
    {
        $this->em->remove($route);
    }

    public function flush(): void
    {
        $this->em->flush();
    }
}
