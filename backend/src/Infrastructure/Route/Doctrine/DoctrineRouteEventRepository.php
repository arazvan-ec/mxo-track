<?php

declare(strict_types=1);

namespace App\Infrastructure\Route\Doctrine;

use App\Domain\Route\Model\RouteEvent;
use App\Domain\Route\Repository\RouteEventRepositoryInterface;
use App\Domain\Route\ValueObject\RouteId;
use App\Entity\Route as RouteEntity;
use App\Entity\RouteEvent as RouteEventEntity;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineRouteEventRepository implements RouteEventRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /** @return list<RouteEvent> */
    public function findByRoute(RouteId $routeId): array
    {
        $routeEntity = $this->findRouteEntity($routeId);
        if ($routeEntity === null) {
            return [];
        }

        $entities = $this->em->createQueryBuilder()
            ->select('e')
            ->from(RouteEventEntity::class, 'e')
            ->where('e.route = :route')
            ->setParameter('route', $routeEntity)
            ->orderBy('e.occurredAt', 'DESC')
            ->getQuery()
            ->getResult();

        return array_values(array_map(fn (RouteEventEntity $e) => $this->toDomain($e), $entities));
    }

    public function save(RouteEvent $event): void
    {
        $routeEntity = $this->findRouteEntity($event->routeId());
        if ($routeEntity === null) {
            throw new \RuntimeException(sprintf('Route %s not found.', $event->routeId()));
        }

        $actorUser = null;
        if ($event->actorUserId() !== null) {
            $actorUser = $this->em->find(User::class, $event->actorUserId());
        }

        $entity = new RouteEventEntity(
            $routeEntity,
            $event->eventType(),
            $event->actorType(),
            $actorUser,
            $event->payload(),
            $event->snapshotMetrics(),
            $event->occurredAt(),
        );

        $this->em->persist($entity);
        $this->em->flush();
    }

    private function findRouteEntity(RouteId $routeId): ?RouteEntity
    {
        try {
            return $this->em->getRepository(RouteEntity::class)
                ->findOneBy(['publicId' => Ulid::fromString((string) $routeId)]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function toDomain(RouteEventEntity $entity): RouteEvent
    {
        return RouteEvent::reconstitute(
            routeId: new RouteId($entity->getRoute()->getPublicIdString()),
            eventType: $entity->getEventType(),
            actorType: $entity->getActorType(),
            actorUserId: $entity->getActorUser()?->getId() !== null ? (int) $entity->getActorUser()->getId() : null,
            payload: $entity->getPayload(),
            snapshotMetrics: $entity->getSnapshotMetrics(),
            occurredAt: $entity->getOccurredAt(),
            createdAt: $entity->getCreatedAt(),
        );
    }
}
