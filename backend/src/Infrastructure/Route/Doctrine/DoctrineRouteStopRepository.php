<?php

declare(strict_types=1);

namespace App\Infrastructure\Route\Doctrine;

use App\Domain\Route\Repository\RouteStopRepositoryInterface;
use App\Entity\Route;
use App\Entity\RouteStop;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineRouteStopRepository implements RouteStopRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function findOneByPublicId(string $publicId): ?RouteStop
    {
        try {
            return $this->em->getRepository(RouteStop::class)
                ->findOneBy(['publicId' => Ulid::fromString($publicId)]);
        } catch (\Throwable) {
            return null;
        }
    }

    public function findByRoute(Route $route): array
    {
        return $this->em->createQueryBuilder()
            ->select('s')
            ->from(RouteStop::class, 's')
            ->where('s.route = :route')
            ->setParameter('route', $route)
            ->orderBy('s.sequence', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(RouteStop $stop): void
    {
        $this->em->persist($stop);
    }

    public function remove(RouteStop $stop): void
    {
        $this->em->remove($stop);
    }

    public function flush(): void
    {
        $this->em->flush();
    }
}
