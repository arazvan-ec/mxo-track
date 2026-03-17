<?php

declare(strict_types=1);

namespace App\Infrastructure\Route\Doctrine\Entity;

use App\Domain\Route\Model\RouteSnapshot;
use App\Domain\Route\ValueObject\RouteId;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'route_snapshot')]
#[ORM\Index(name: 'idx_route_snapshot_route', columns: ['route_id'])]
class RouteSnapshotEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: RouteEntity::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private RouteEntity $route;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $polyline = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $originalPolyline = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $actualPolyline = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $distanceBeforeKm = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $distanceAfterKm = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 1, nullable: true)]
    private ?string $savingsPercent = null;

    #[ORM\Column(nullable: true)]
    private ?int $drivingTimeMinutes = null;

    #[ORM\Column(nullable: true)]
    private ?int $deliveryTimeMinutes = null;

    #[ORM\Column(nullable: true)]
    private ?int $totalTimeMinutes = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $originalStopOrder = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $stopStates = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $etas = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $capacityValidation = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    private function __construct()
    {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoute(): RouteEntity
    {
        return $this->route;
    }

    // ── Domain ↔ Doctrine Mapping ──

    public function toDomain(): RouteSnapshot
    {
        return RouteSnapshot::reconstitute(
            routeId: new RouteId((string) $this->route->getPublicId()),
            polyline: $this->polyline,
            originalPolyline: $this->originalPolyline,
            actualPolyline: $this->actualPolyline,
            distanceBeforeKm: $this->distanceBeforeKm !== null ? (float) $this->distanceBeforeKm : null,
            distanceAfterKm: $this->distanceAfterKm !== null ? (float) $this->distanceAfterKm : null,
            savingsPercent: $this->savingsPercent !== null ? (float) $this->savingsPercent : null,
            drivingTimeMinutes: $this->drivingTimeMinutes,
            deliveryTimeMinutes: $this->deliveryTimeMinutes,
            totalTimeMinutes: $this->totalTimeMinutes,
            originalStopOrder: $this->originalStopOrder,
            stopStates: $this->stopStates,
            etas: $this->etas,
            capacityValidation: $this->capacityValidation,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
        );
    }

    public static function fromDomain(RouteSnapshot $snapshot, RouteEntity $routeEntity): self
    {
        $entity = new self();
        $entity->route = $routeEntity;
        $entity->createdAt = $snapshot->createdAt();
        $entity->updatedAt = $snapshot->updatedAt();
        $entity->updateFromDomain($snapshot);

        return $entity;
    }

    public function updateFromDomain(RouteSnapshot $snapshot): void
    {
        $this->polyline = $snapshot->polyline();
        $this->originalPolyline = $snapshot->originalPolyline();
        $this->actualPolyline = $snapshot->actualPolyline();
        $this->distanceBeforeKm = $snapshot->distanceBeforeKm() !== null ? (string) $snapshot->distanceBeforeKm() : null;
        $this->distanceAfterKm = $snapshot->distanceAfterKm() !== null ? (string) $snapshot->distanceAfterKm() : null;
        $this->savingsPercent = $snapshot->savingsPercent() !== null ? (string) $snapshot->savingsPercent() : null;
        $this->drivingTimeMinutes = $snapshot->drivingTimeMinutes();
        $this->deliveryTimeMinutes = $snapshot->deliveryTimeMinutes();
        $this->totalTimeMinutes = $snapshot->totalTimeMinutes();
        $this->originalStopOrder = $snapshot->originalStopOrder();
        $this->stopStates = $snapshot->stopStates();
        $this->etas = $snapshot->etas();
        $this->capacityValidation = $snapshot->capacityValidation();
        $this->updatedAt = $snapshot->updatedAt();
    }
}
