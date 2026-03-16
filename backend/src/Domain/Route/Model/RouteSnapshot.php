<?php

declare(strict_types=1);

namespace App\Domain\Route\Model;

use App\Domain\Route\ValueObject\RouteId;

final class RouteSnapshot
{
    private ?string $polyline = null;
    private ?string $originalPolyline = null;
    private ?string $actualPolyline = null;
    private ?float $distanceBeforeKm = null;
    private ?float $distanceAfterKm = null;
    private ?float $savingsPercent = null;
    private ?int $drivingTimeMinutes = null;
    private ?int $deliveryTimeMinutes = null;
    private ?int $totalTimeMinutes = null;
    private ?array $originalStopOrder = null;
    private ?array $stopStates = null;
    private ?array $etas = null;
    private ?array $capacityValidation = null;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        private readonly RouteId $routeId,
    ) {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public static function reconstitute(
        RouteId $routeId,
        ?string $polyline,
        ?string $originalPolyline,
        ?string $actualPolyline,
        ?float $distanceBeforeKm,
        ?float $distanceAfterKm,
        ?float $savingsPercent,
        ?int $drivingTimeMinutes,
        ?int $deliveryTimeMinutes,
        ?int $totalTimeMinutes,
        ?array $originalStopOrder,
        ?array $stopStates,
        ?array $etas,
        ?array $capacityValidation,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $snapshot = new self($routeId);
        $snapshot->polyline = $polyline;
        $snapshot->originalPolyline = $originalPolyline;
        $snapshot->actualPolyline = $actualPolyline;
        $snapshot->distanceBeforeKm = $distanceBeforeKm;
        $snapshot->distanceAfterKm = $distanceAfterKm;
        $snapshot->savingsPercent = $savingsPercent;
        $snapshot->drivingTimeMinutes = $drivingTimeMinutes;
        $snapshot->deliveryTimeMinutes = $deliveryTimeMinutes;
        $snapshot->totalTimeMinutes = $totalTimeMinutes;
        $snapshot->originalStopOrder = $originalStopOrder;
        $snapshot->stopStates = $stopStates;
        $snapshot->etas = $etas;
        $snapshot->capacityValidation = $capacityValidation;
        $snapshot->createdAt = $createdAt;
        $snapshot->updatedAt = $updatedAt;

        return $snapshot;
    }

    public function routeId(): RouteId { return $this->routeId; }

    public function polyline(): ?string { return $this->polyline; }
    public function setPolyline(?string $polyline): void { $this->polyline = $polyline; }

    public function originalPolyline(): ?string { return $this->originalPolyline; }
    public function setOriginalPolyline(?string $v): void { $this->originalPolyline = $v; }

    public function actualPolyline(): ?string { return $this->actualPolyline; }
    public function setActualPolyline(?string $v): void { $this->actualPolyline = $v; }

    public function distanceBeforeKm(): ?float { return $this->distanceBeforeKm; }
    public function setDistanceBeforeKm(?float $v): void { $this->distanceBeforeKm = $v; }

    public function distanceAfterKm(): ?float { return $this->distanceAfterKm; }
    public function setDistanceAfterKm(?float $v): void { $this->distanceAfterKm = $v; }

    public function savingsPercent(): ?float { return $this->savingsPercent; }
    public function setSavingsPercent(?float $v): void { $this->savingsPercent = $v; }

    public function drivingTimeMinutes(): ?int { return $this->drivingTimeMinutes; }
    public function setDrivingTimeMinutes(?int $v): void { $this->drivingTimeMinutes = $v; }

    public function deliveryTimeMinutes(): ?int { return $this->deliveryTimeMinutes; }
    public function setDeliveryTimeMinutes(?int $v): void { $this->deliveryTimeMinutes = $v; }

    public function totalTimeMinutes(): ?int { return $this->totalTimeMinutes; }
    public function setTotalTimeMinutes(?int $v): void { $this->totalTimeMinutes = $v; }

    /** @return array<int, array<string, mixed>>|null */
    public function originalStopOrder(): ?array { return $this->originalStopOrder; }
    /** @param array<int, array<string, mixed>>|null $v */
    public function setOriginalStopOrder(?array $v): void { $this->originalStopOrder = $v; }

    /** @return array<int, array<string, mixed>>|null */
    public function stopStates(): ?array { return $this->stopStates; }
    /** @param array<int, array<string, mixed>>|null $v */
    public function setStopStates(?array $v): void { $this->stopStates = $v; $this->updatedAt = new \DateTimeImmutable(); }

    public function etas(): ?array { return $this->etas; }
    public function setEtas(?array $v): void { $this->etas = $v; $this->updatedAt = new \DateTimeImmutable(); }

    public function capacityValidation(): ?array { return $this->capacityValidation; }
    public function setCapacityValidation(?array $v): void { $this->capacityValidation = $v; }

    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }
}
