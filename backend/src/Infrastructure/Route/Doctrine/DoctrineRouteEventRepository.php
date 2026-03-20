<?php

declare(strict_types=1);

namespace App\Infrastructure\Route\Doctrine;

use App\Domain\Route\Repository\RouteEventRepositoryInterface;
use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteEvent;
use App\Enum\RouteEventType;
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

    public function findLastByTypeForRoute(Route $route, RouteEventType $type): ?RouteEvent
    {
        return $this->em->createQueryBuilder()
            ->select('e')
            ->from(RouteEvent::class, 'e')
            ->where('e.route = :route')
            ->andWhere('e.eventType = :type')
            ->setParameter('route', $route)
            ->setParameter('type', $type->value)
            ->orderBy('e.occurredAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
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
