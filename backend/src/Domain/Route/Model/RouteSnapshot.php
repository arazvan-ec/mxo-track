<?php

declare(strict_types=1);

namespace App\Domain\Route\Model;

use DateTimeImmutable;

/**
 * Immutable operational snapshot of a Route after optimization.
 * Stores polylines, metrics, stop states, ETAs, and capacity validation.
 *
 * Persistence is handled via external XML mapping (no ORM attributes).
 */
class RouteSnapshot
{
    private ?int $id = null;
    private Route $route;

    // ── Polylines (encoded Google format from OSRM) ──

    private ?string $polyline = null;
    private ?string $originalPolyline = null;
    private ?string $actualPolyline = null;

    // ── Optimization metrics ──

    private ?string $distanceBeforeKm = null;
    private ?string $distanceAfterKm = null;
    private ?string $savingsPercent = null;

    // ── Timing ──

    private ?int $drivingTimeMinutes = null;
    private ?int $deliveryTimeMinutes = null;
    private ?int $totalTimeMinutes = null;

    // ── Stop snapshots ──

    private ?array $originalStopOrder = null;
    private ?array $stopStates = null;

    // ── ETAs ──

    private ?array $etas = null;

    // ── Capacity ──

    private ?array $capacityValidation = null;

    // ── Timestamps ──

    private DateTimeImmutable $createdAt;
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

    /** @return array<string, array{eta: string, minutes: int, distance_km: float}>|null */
    public function getEtas(): ?array { return $this->etas; }
    /** @param array<string, array{eta: string, minutes: int, distance_km: float}>|null $v */
    public function setEtas(?array $v): void { $this->etas = $v; $this->updatedAt = new DateTimeImmutable(); }

    /** @return array<string, mixed>|null */
    public function getCapacityValidation(): ?array { return $this->capacityValidation; }
    /** @param array<string, mixed>|null $v */
    public function setCapacityValidation(?array $v): void { $this->capacityValidation = $v; }

    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): DateTimeImmutable { return $this->updatedAt; }

    public function touch(): void { $this->updatedAt = new DateTimeImmutable(); }
}
