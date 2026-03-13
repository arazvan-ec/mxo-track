<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RouteSnapshotRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RouteSnapshotRepository::class)]
#[ORM\Table(name: 'route_snapshot')]
#[ORM\Index(name: 'idx_route_snapshot_route', columns: ['route_id'])]
class RouteSnapshot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Route::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Route $route;

    // ── Polylines (encoded Google format from OSRM) ──

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $polyline = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $originalPolyline = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $actualPolyline = null;

    // ── Optimization metrics ──

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $distanceBeforeKm = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $distanceAfterKm = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 1, nullable: true)]
    private ?string $savingsPercent = null;

    // ── Timing ──

    #[ORM\Column(nullable: true)]
    private ?int $drivingTimeMinutes = null;

    #[ORM\Column(nullable: true)]
    private ?int $deliveryTimeMinutes = null;

    #[ORM\Column(nullable: true)]
    private ?int $totalTimeMinutes = null;

    // ── Stop snapshots ──

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $originalStopOrder = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $stopStates = null;

    // ── Capacity ──

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $capacityValidation = null;

    // ── Timestamps ──

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    public function __construct(Route $route)
    {
        $this->route = $route;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getRoute(): Route { return $this->route; }

    public function getPolyline(): ?string { return $this->polyline; }
    public function setPolyline(?string $polyline): void { $this->polyline = $polyline; }

    public function getOriginalPolyline(): ?string { return $this->originalPolyline; }
    public function setOriginalPolyline(?string $v): void { $this->originalPolyline = $v; }

    public function getActualPolyline(): ?string { return $this->actualPolyline; }
    public function setActualPolyline(?string $v): void { $this->actualPolyline = $v; }

    public function getDistanceBeforeKm(): ?float { return $this->distanceBeforeKm !== null ? (float) $this->distanceBeforeKm : null; }
    public function setDistanceBeforeKm(?float $v): void { $this->distanceBeforeKm = $v !== null ? (string) $v : null; }

    public function getDistanceAfterKm(): ?float { return $this->distanceAfterKm !== null ? (float) $this->distanceAfterKm : null; }
    public function setDistanceAfterKm(?float $v): void { $this->distanceAfterKm = $v !== null ? (string) $v : null; }

    public function getSavingsPercent(): ?float { return $this->savingsPercent !== null ? (float) $this->savingsPercent : null; }
    public function setSavingsPercent(?float $v): void { $this->savingsPercent = $v !== null ? (string) $v : null; }

    public function getDrivingTimeMinutes(): ?int { return $this->drivingTimeMinutes; }
    public function setDrivingTimeMinutes(?int $v): void { $this->drivingTimeMinutes = $v; }

    public function getDeliveryTimeMinutes(): ?int { return $this->deliveryTimeMinutes; }
    public function setDeliveryTimeMinutes(?int $v): void { $this->deliveryTimeMinutes = $v; }

    public function getTotalTimeMinutes(): ?int { return $this->totalTimeMinutes; }
    public function setTotalTimeMinutes(?int $v): void { $this->totalTimeMinutes = $v; }

    /** @return array<int, array<string, mixed>>|null */
    public function getOriginalStopOrder(): ?array { return $this->originalStopOrder; }
    /** @param array<int, array<string, mixed>>|null $v */
    public function setOriginalStopOrder(?array $v): void { $this->originalStopOrder = $v; }

    /** @return array<int, array<string, mixed>>|null */
    public function getStopStates(): ?array { return $this->stopStates; }
    /** @param array<int, array<string, mixed>>|null $v */
    public function setStopStates(?array $v): void { $this->stopStates = $v; $this->updatedAt = new DateTimeImmutable(); }

    /** @return array<string, mixed>|null */
    public function getCapacityValidation(): ?array { return $this->capacityValidation; }
    /** @param array<string, mixed>|null $v */
    public function setCapacityValidation(?array $v): void { $this->capacityValidation = $v; }

    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): DateTimeImmutable { return $this->updatedAt; }

    public function touch(): void { $this->updatedAt = new DateTimeImmutable(); }
}
