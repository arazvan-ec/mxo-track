<?php

declare(strict_types=1);

namespace App\Infrastructure\Route\Doctrine;

use App\Domain\Route\Model\Route;
use App\Domain\Route\Repository\RouteRepositoryInterface;
use App\Domain\Route\ValueObject\Capacity;
use App\Domain\Route\ValueObject\Distance;
use App\Domain\Route\ValueObject\RouteId;
use App\Entity\Route as RouteEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineRouteRepository implements RouteRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function findById(RouteId $id): ?Route
    {
        $entity = $this->findEntity($id);

        return $entity !== null ? $this->toDomain($entity) : null;
    }

    public function save(Route $route): void
    {
        $entity = $this->findEntity($route->id());

        if ($entity !== null) {
            $this->updateEntity($entity, $route);
        } else {
            $entity = $this->createEntity($route);
            $this->em->persist($entity);
        }

        $this->em->flush();
    }

    public function remove(Route $route): void
    {
        $entity = $this->findEntity($route->id());

        if ($entity !== null) {
            $this->em->remove($entity);
            $this->em->flush();
        }
    }

    private function findEntity(RouteId $id): ?RouteEntity
    {
        try {
            return $this->em->getRepository(RouteEntity::class)
                ->findOneBy(['publicId' => Ulid::fromString((string) $id)]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function toDomain(RouteEntity $entity): Route
    {
        $capacity = null;
        $weightKg = $entity->getTotalWeightKg();
        $volumeM3 = $entity->getTotalVolumeM3();
        $parcels = $entity->getTotalParcels();
        if ($weightKg !== null || $volumeM3 !== null || $parcels !== null) {
            $capacity = new Capacity($weightKg ?? 0.0, $volumeM3 ?? 0.0, $parcels ?? 0);
        }

        $distanceKm = $entity->getTotalDistanceKm();
        $distance = $distanceKm !== null ? new Distance($distanceKm) : null;

        return Route::reconstitute(
            id: new RouteId($entity->getPublicIdString()),
            name: $entity->getName(),
            status: $entity->getStatus(),
            driverId: $entity->getDriver()?->getId() !== null ? (int) $entity->getDriver()->getId() : null,
            vehicleId: $entity->getVehicle()?->getId() !== null ? (int) $entity->getVehicle()->getId() : null,
            customerId: $entity->getCustomer()?->getId() !== null ? (int) $entity->getCustomer()->getId() : null,
            originLocationId: $entity->getOriginLocation()?->getId() !== null ? (int) $entity->getOriginLocation()->getId() : null,
            capacity: $capacity,
            totalDistance: $distance,
            estimatedDurationMinutes: $entity->getEstimatedDurationMinutes(),
            aiAnalysis: $entity->getAiAnalysis(),
            autoReoptimize: $entity->isAutoReoptimize(),
            startAt: $entity->getStartAt(),
            endAt: $entity->getEndAt(),
            deletedAt: $entity->getDeletedAt(),
        );
    }

    private function updateEntity(RouteEntity $entity, Route $route): void
    {
        $entity->setName($route->name());
        $entity->setStatus($route->status());
        $entity->setStartAt($route->startAt());
        $entity->setEndAt($route->endAt());
        $entity->setEstimatedDurationMinutes($route->estimatedDurationMinutes());
        $entity->setAiAnalysis($route->aiAnalysis());
        $entity->setAutoReoptimize($route->autoReoptimize());

        $capacity = $route->capacity();
        $entity->setTotalWeightKg($capacity?->weightKg);
        $entity->setTotalVolumeM3($capacity?->volumeM3);
        $entity->setTotalParcels($capacity?->parcels);

        $distance = $route->totalDistance();
        $entity->setTotalDistanceKm($distance?->km);

        // Resolve ManyToOne relations from int IDs
        $this->resolveRelations($entity, $route);

        if ($route->deletedAt() !== null) {
            $entity->softDelete();
        }
    }

    private function createEntity(Route $route): RouteEntity
    {
        $entity = new RouteEntity($route->name());
        // publicId is set via PrePersist; override with domain's RouteId
        $ref = new \ReflectionProperty(RouteEntity::class, 'publicId');
        $ref->setValue($entity, Ulid::fromString((string) $route->id()));

        $this->updateEntity($entity, $route);

        return $entity;
    }

    private function resolveRelations(RouteEntity $entity, Route $route): void
    {
        if ($route->driverId() !== null) {
            $driver = $this->em->find(\App\Entity\User::class, $route->driverId());
            $entity->setDriver($driver);
        } else {
            $entity->setDriver(null);
        }

        if ($route->vehicleId() !== null) {
            $vehicle = $this->em->find(\App\Entity\Vehicle::class, $route->vehicleId());
            $entity->setVehicle($vehicle);
        } else {
            $entity->setVehicle(null);
        }

        if ($route->customerId() !== null) {
            $customer = $this->em->find(\App\Entity\Customer::class, $route->customerId());
            $entity->setCustomer($customer);
        } else {
            $entity->setCustomer(null);
        }

        if ($route->originLocationId() !== null) {
            $location = $this->em->find(\App\Entity\CustomerLocation::class, $route->originLocationId());
            $entity->setOriginLocation($location);
        } else {
            $entity->setOriginLocation(null);
        }
    }
}
