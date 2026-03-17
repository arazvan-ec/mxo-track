<?php

declare(strict_types=1);

namespace App\Infrastructure\Route\Doctrine;

use App\Domain\Route\Model\RouteStop;
use App\Domain\Route\Repository\RouteStopRepositoryInterface;
use App\Domain\Route\ValueObject\Coordinate;
use App\Domain\Route\ValueObject\RouteId;
use App\Domain\Route\ValueObject\StopId;
use App\Domain\Route\ValueObject\TimeWindow;
use App\Entity\Route as RouteEntity;
use App\Entity\RouteStop as RouteStopEntity;
use App\Entity\Shipment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineRouteStopRepository implements RouteStopRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function findById(StopId $id): ?RouteStop
    {
        $entity = $this->findEntity($id);

        return $entity !== null ? $this->toDomain($entity) : null;
    }

    /** @return list<RouteStop> */
    public function findByRoute(RouteId $routeId): array
    {
        $routeEntity = $this->findRouteEntity($routeId);
        if ($routeEntity === null) {
            return [];
        }

        $entities = $this->em->getRepository(RouteStopEntity::class)
            ->findBy(['route' => $routeEntity], ['sequence' => 'ASC']);

        return array_values(array_map(fn (RouteStopEntity $e) => $this->toDomain($e), $entities));
    }

    public function save(RouteStop $stop): void
    {
        $entity = $this->findEntity($stop->id());

        if ($entity !== null) {
            $this->updateEntity($entity, $stop);
        } else {
            $routeEntity = $this->findRouteEntity($stop->routeId());
            if ($routeEntity === null) {
                throw new \RuntimeException(sprintf('Route %s not found.', $stop->routeId()));
            }
            $entity = $this->createEntity($stop, $routeEntity);
            $this->em->persist($entity);
        }

        $this->em->flush();
    }

    /** @param list<RouteStop> $stops */
    public function saveAll(array $stops): void
    {
        foreach ($stops as $stop) {
            $entity = $this->findEntity($stop->id());

            if ($entity !== null) {
                $this->updateEntity($entity, $stop);
            } else {
                $routeEntity = $this->findRouteEntity($stop->routeId());
                if ($routeEntity === null) {
                    throw new \RuntimeException(sprintf('Route %s not found.', $stop->routeId()));
                }
                $entity = $this->createEntity($stop, $routeEntity);
                $this->em->persist($entity);
            }
        }

        $this->em->flush();
    }

    public function remove(RouteStop $stop): void
    {
        $entity = $this->findEntity($stop->id());

        if ($entity !== null) {
            $this->em->remove($entity);
            $this->em->flush();
        }
    }

    public function nextSequence(RouteId $routeId): int
    {
        $routeEntity = $this->findRouteEntity($routeId);
        if ($routeEntity === null) {
            return 1;
        }

        $max = $this->em->createQueryBuilder()
            ->select('MAX(s.sequence)')
            ->from(RouteStopEntity::class, 's')
            ->where('s.route = :route')
            ->setParameter('route', $routeEntity)
            ->getQuery()
            ->getSingleScalarResult();

        return ($max !== null ? (int) $max : 0) + 1;
    }

    private function findEntity(StopId $id): ?RouteStopEntity
    {
        try {
            return $this->em->getRepository(RouteStopEntity::class)
                ->findOneBy(['publicId' => Ulid::fromString((string) $id)]);
        } catch (\Throwable) {
            return null;
        }
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

    private function toDomain(RouteStopEntity $entity): RouteStop
    {
        $coordinate = ($entity->getLatitude() !== null && $entity->getLongitude() !== null)
            ? new Coordinate($entity->getLatitude(), $entity->getLongitude())
            : null;

        $deliveryWindow = ($entity->getDeliveryWindowStart() !== null && $entity->getDeliveryWindowEnd() !== null)
            ? new TimeWindow($entity->getDeliveryWindowStart(), $entity->getDeliveryWindowEnd())
            : null;

        return RouteStop::reconstitute(
            id: new StopId($entity->getPublicIdString()),
            routeId: new RouteId($entity->getRoute()->getPublicIdString()),
            sequence: $entity->getSequence(),
            address: $entity->getAddress(),
            status: $entity->getStatus(),
            coordinate: $coordinate,
            recipientName: $entity->getRecipientName(),
            recipientPhone: $entity->getRecipientPhone(),
            notes: $entity->getNotes(),
            aiNotes: $entity->getAiNotes(),
            isOrigin: $entity->isOrigin(),
            deliveredAt: $entity->getDeliveredAt(),
            exceptionCode: $entity->getExceptionCode(),
            exceptionNotes: $entity->getExceptionNotes(),
            deliveryWindow: $deliveryWindow,
            shipmentPublicId: $entity->getShipment()?->getPublicIdString(),
        );
    }

    private function updateEntity(RouteStopEntity $entity, RouteStop $stop): void
    {
        $entity->setSequence($stop->sequence());
        $entity->setAddress($stop->address());
        $entity->setRecipientName($stop->recipientName());
        $entity->setRecipientPhone($stop->recipientPhone());
        $entity->setNotes($stop->notes());
        $entity->setAiNotes($stop->aiNotes());
        $entity->setOrigin($stop->isOrigin());

        $coord = $stop->coordinate();
        $entity->setLatitude($coord?->latitude);
        $entity->setLongitude($coord?->longitude);

        $window = $stop->deliveryWindow();
        $entity->setDeliveryWindowStart($window?->start);
        $entity->setDeliveryWindowEnd($window?->end);

        // Sync status-related fields via domain methods on entity
        // We need to reflect the domain model's status directly
        $this->syncStatus($entity, $stop);

        // Resolve shipment relation
        if ($stop->shipmentPublicId() !== null) {
            $shipment = $this->em->getRepository(Shipment::class)
                ->findOneBy(['publicId' => Ulid::fromString($stop->shipmentPublicId())]);
            $entity->setShipment($shipment);
        } else {
            $entity->setShipment(null);
        }
    }

    private function syncStatus(RouteStopEntity $entity, RouteStop $stop): void
    {
        // The old entity uses markDelivered()/markException() for state changes.
        // We replicate the domain state directly since we own the full state.
        $status = $stop->status();
        $currentStatus = $entity->getStatus();

        if ($status === \App\Enum\RouteStopStatus::DELIVERED && $currentStatus !== \App\Enum\RouteStopStatus::DELIVERED) {
            $entity->markDelivered();
        } elseif ($status === \App\Enum\RouteStopStatus::EXCEPTION) {
            $entity->markException(
                $stop->exceptionCode() ?? \App\Enum\ExceptionCode::ABSENT,
                $stop->exceptionNotes() ?? '',
            );
        }
    }

    private function createEntity(RouteStop $stop, RouteEntity $routeEntity): RouteStopEntity
    {
        $entity = new RouteStopEntity($routeEntity, $stop->sequence(), $stop->address());

        // Override publicId
        $ref = new \ReflectionProperty(RouteStopEntity::class, 'publicId');
        $ref->setValue($entity, Ulid::fromString((string) $stop->id()));

        $this->updateEntity($entity, $stop);

        return $entity;
    }
}
