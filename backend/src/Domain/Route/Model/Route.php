<?php

declare(strict_types=1);

namespace App\Domain\Route\Model;

use App\Domain\Route\ValueObject\Capacity;
use App\Domain\Route\ValueObject\Distance;
use App\Domain\Route\ValueObject\RouteId;
use App\Enum\RouteStatus;

final class Route
{
    private RouteStatus $status;
    private ?int $driverId = null;
    private ?int $vehicleId = null;
    private ?int $customerId = null;
    private ?int $originLocationId = null;
    private ?Capacity $capacity = null;
    private ?Distance $totalDistance = null;
    private ?int $estimatedDurationMinutes = null;
    private ?array $aiAnalysis = null;
    private bool $autoReoptimize = false;
    private ?\DateTimeImmutable $startAt = null;
    private ?\DateTimeImmutable $endAt = null;
    private ?\DateTimeImmutable $deletedAt = null;

    public function __construct(
        private readonly RouteId $id,
        private string $name,
    ) {
        $this->status = RouteStatus::PLANNED;
    }

    public static function reconstitute(
        RouteId $id,
        string $name,
        RouteStatus $status,
        ?int $driverId,
        ?int $vehicleId,
        ?int $customerId,
        ?int $originLocationId,
        ?Capacity $capacity,
        ?Distance $totalDistance,
        ?int $estimatedDurationMinutes,
        ?array $aiAnalysis,
        bool $autoReoptimize,
        ?\DateTimeImmutable $startAt,
        ?\DateTimeImmutable $endAt,
        ?\DateTimeImmutable $deletedAt,
    ): self {
        $route = new self($id, $name);
        $route->status = $status;
        $route->driverId = $driverId;
        $route->vehicleId = $vehicleId;
        $route->customerId = $customerId;
        $route->originLocationId = $originLocationId;
        $route->capacity = $capacity;
        $route->totalDistance = $totalDistance;
        $route->estimatedDurationMinutes = $estimatedDurationMinutes;
        $route->aiAnalysis = $aiAnalysis;
        $route->autoReoptimize = $autoReoptimize;
        $route->startAt = $startAt;
        $route->endAt = $endAt;
        $route->deletedAt = $deletedAt;

        return $route;
    }

    public function id(): RouteId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function status(): RouteStatus
    {
        return $this->status;
    }

    public function start(): void
    {
        if ($this->status !== RouteStatus::PLANNED) {
            throw new \DomainException('Route can only start from PLANNED status.');
        }
        $this->status = RouteStatus::ACTIVE;
        $this->startAt = new \DateTimeImmutable();
    }

    public function finish(): void
    {
        if ($this->status !== RouteStatus::ACTIVE) {
            throw new \DomainException('Route can only finish from ACTIVE status.');
        }
        $this->status = RouteStatus::DONE;
        $this->endAt = new \DateTimeImmutable();
    }

    public function driverId(): ?int
    {
        return $this->driverId;
    }

    public function assignDriver(?int $driverId): void
    {
        $this->driverId = $driverId;
    }

    public function vehicleId(): ?int
    {
        return $this->vehicleId;
    }

    public function assignVehicle(?int $vehicleId): void
    {
        $this->vehicleId = $vehicleId;
    }

    public function customerId(): ?int
    {
        return $this->customerId;
    }

    public function setCustomerId(?int $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function originLocationId(): ?int
    {
        return $this->originLocationId;
    }

    public function setOriginLocationId(?int $originLocationId): void
    {
        $this->originLocationId = $originLocationId;
    }

    public function capacity(): ?Capacity
    {
        return $this->capacity;
    }

    public function setCapacity(?Capacity $capacity): void
    {
        $this->capacity = $capacity;
    }

    public function totalDistance(): ?Distance
    {
        return $this->totalDistance;
    }

    public function setTotalDistance(?Distance $totalDistance): void
    {
        $this->totalDistance = $totalDistance;
    }

    public function estimatedDurationMinutes(): ?int
    {
        return $this->estimatedDurationMinutes;
    }

    public function setEstimatedDurationMinutes(?int $minutes): void
    {
        $this->estimatedDurationMinutes = $minutes;
    }

    /** @return array<string, mixed>|null */
    public function aiAnalysis(): ?array
    {
        return $this->aiAnalysis;
    }

    /** @param array<string, mixed>|null $aiAnalysis */
    public function setAiAnalysis(?array $aiAnalysis): void
    {
        $this->aiAnalysis = $aiAnalysis;
    }

    public function autoReoptimize(): bool
    {
        return $this->autoReoptimize;
    }

    public function setAutoReoptimize(bool $autoReoptimize): void
    {
        $this->autoReoptimize = $autoReoptimize;
    }

    public function startAt(): ?\DateTimeImmutable
    {
        return $this->startAt;
    }

    public function endAt(): ?\DateTimeImmutable
    {
        return $this->endAt;
    }

    public function deletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function softDelete(): void
    {
        $this->deletedAt = new \DateTimeImmutable();
    }
}
