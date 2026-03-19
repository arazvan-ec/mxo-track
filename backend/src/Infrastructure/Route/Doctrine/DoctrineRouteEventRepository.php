<?php

declare(strict_types=1);

namespace App\Infrastructure\Route\Doctrine;

use App\Domain\Route\Repository\RouteEventRepositoryInterface;
use App\Entity\Route;
use App\Entity\RouteEvent;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineRouteEventRepository implements RouteEventRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function findByRoute(Route $route): array
    {
        return $this->em->createQueryBuilder()
            ->select('e')
            ->from(RouteEvent::class, 'e')
            ->where('e.route = :route')
            ->setParameter('route', $route)
            ->orderBy('e.occurredAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(RouteEvent $event): void
    {
        $this->em->persist($event);
    }

    public function flush(): void
    {
        $this->em->flush();
    }
}
