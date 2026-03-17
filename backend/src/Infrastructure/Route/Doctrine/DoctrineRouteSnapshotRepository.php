<?php

declare(strict_types=1);

namespace App\Infrastructure\Route\Doctrine;

use App\Domain\Route\Model\RouteSnapshot;
use App\Domain\Route\Repository\RouteSnapshotRepositoryInterface;
use App\Domain\Route\ValueObject\RouteId;
use App\Entity\Route as RouteEntity;
use App\Entity\RouteSnapshot as RouteSnapshotEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineRouteSnapshotRepository implements RouteSnapshotRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function findByRoute(RouteId $routeId): ?RouteSnapshot
    {
        $routeEntity = $this->findRouteEntity($routeId);
        if ($routeEntity === null) {
            return null;
        }

        $entity = $this->em->getRepository(RouteSnapshotEntity::class)
            ->findOneBy(['route' => $routeEntity]);

        return $entity !== null ? $this->toDomain($entity) : null;
    }

    public function save(RouteSnapshot $snapshot): void
    {
        $routeEntity = $this->findRouteEntity($snapshot->routeId());
        if ($routeEntity === null) {
            throw new \RuntimeException(sprintf('Route %s not found.', $snapshot->routeId()));
        }

        $entity = $this->em->getRepository(RouteSnapshotEntity::class)
            ->findOneBy(['route' => $routeEntity]);

        if ($entity !== null) {
            $this->updateEntity($entity, $snapshot);
        } else {
            $entity = new RouteSnapshotEntity($routeEntity);
            $this->updateEntity($entity, $snapshot);
            $this->em->persist($entity);
        }

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

    private function toDomain(RouteSnapshotEntity $entity): RouteSnapshot
    {
        return RouteSnapshot::reconstitute(
            routeId: new RouteId($entity->getRoute()->getPublicIdString()),
            polyline: $entity->getPolyline(),
            originalPolyline: $entity->getOriginalPolyline(),
            actualPolyline: $entity->getActualPolyline(),
            distanceBeforeKm: $entity->getDistanceBeforeKm(),
            distanceAfterKm: $entity->getDistanceAfterKm(),
            savingsPercent: $entity->getSavingsPercent(),
            drivingTimeMinutes: $entity->getDrivingTimeMinutes(),
            deliveryTimeMinutes: $entity->getDeliveryTimeMinutes(),
            totalTimeMinutes: $entity->getTotalTimeMinutes(),
            originalStopOrder: $entity->getOriginalStopOrder(),
            stopStates: $entity->getStopStates(),
            etas: $entity->getEtas(),
            capacityValidation: $entity->getCapacityValidation(),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }

    private function updateEntity(RouteSnapshotEntity $entity, RouteSnapshot $snapshot): void
    {
        $entity->setPolyline($snapshot->polyline());
        $entity->setOriginalPolyline($snapshot->originalPolyline());
        $entity->setActualPolyline($snapshot->actualPolyline());
        $entity->setDistanceBeforeKm($snapshot->distanceBeforeKm());
        $entity->setDistanceAfterKm($snapshot->distanceAfterKm());
        $entity->setSavingsPercent($snapshot->savingsPercent());
        $entity->setDrivingTimeMinutes($snapshot->drivingTimeMinutes());
        $entity->setDeliveryTimeMinutes($snapshot->deliveryTimeMinutes());
        $entity->setTotalTimeMinutes($snapshot->totalTimeMinutes());
        $entity->setOriginalStopOrder($snapshot->originalStopOrder());
        $entity->setStopStates($snapshot->stopStates());
        $entity->setEtas($snapshot->etas());
        $entity->setCapacityValidation($snapshot->capacityValidation());
        $entity->touch();
    }
}
