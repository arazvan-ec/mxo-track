<?php

declare(strict_types=1);

namespace App\Domain\Route\Model;

use App\Entity\Customer;
use App\Entity\CustomerLocation;
use App\Entity\SoftDeletableInterface;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Enum\RouteEventType;
use App\Enum\RouteStatus;
use DateTimeImmutable;
use Symfony\Component\Uid\Ulid;

/**
 * Route aggregate root — domain POPO.
 * Persistence handled via external XML mapping (no ORM attributes).
 */
class Route implements SoftDeletableInterface
{
    private ?string $id = null;
    private Ulid $publicId;
    private string $name;
    private int $version = 1;
    private RouteStatus $status = RouteStatus::PLANNED;
    private ?User $driver = null;
    private ?Vehicle $vehicle = null;
    private ?DateTimeImmutable $startAt = null;
    private ?DateTimeImmutable $endAt = null;
    private ?Customer $customer = null;
    private ?CustomerLocation $originLocation = null;
    private ?string $totalWeightKg = null;
    private ?string $totalVolumeM3 = null;
    private ?int $totalParcels = null;
    private ?string $totalDistanceKm = null;
    private ?int $estimatedDurationMinutes = null;
    private ?array $aiAnalysis = null;
    private bool $autoReoptimize = false;
    private ?DateTimeImmutable $deletedAt = null;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->publicId = new Ulid();
    }

    // ── Identity ──

    public function getId(): ?string { return $this->id; }
    public function getPublicId(): Ulid { return $this->publicId; }
    public function getPublicIdString(): string { return (string) $this->publicId; }
    public function getVersion(): int { return $this->version; }

    /**
     * Ensure publicId is initialized (called via lifecycle callback on prePersist).
     */
    public function initializePublicId(): void
    {
        $this->publicId ??= new Ulid();
    }

    // ── Soft Delete ──

    public function getDeletedAt(): ?DateTimeImmutable { return $this->deletedAt; }
    public function isDeleted(): bool { return $this->deletedAt !== null; }
    public function softDelete(): void { $this->deletedAt = new DateTimeImmutable(); }

    // ── Accessors ──

    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
    public function getStatus(): RouteStatus { return $this->status; }
    public function setStatus(RouteStatus $status): void { $this->status = $status; }
    public function getDriver(): ?User { return $this->driver; }
    public function setDriver(?User $driver): void { $this->driver = $driver; }
    public function getVehicle(): ?Vehicle { return $this->vehicle; }
    public function setVehicle(?Vehicle $vehicle): void { $this->vehicle = $vehicle; }
    public function getStartAt(): ?DateTimeImmutable { return $this->startAt; }
    public function setStartAt(?DateTimeImmutable $startAt): void { $this->startAt = $startAt; }
    public function getEndAt(): ?DateTimeImmutable { return $this->endAt; }
    public function setEndAt(?DateTimeImmutable $endAt): void { $this->endAt = $endAt; }
    public function getCustomer(): ?Customer { return $this->customer; }
    public function setCustomer(?Customer $customer): void { $this->customer = $customer; }
    public function getOriginLocation(): ?CustomerLocation { return $this->originLocation; }
    public function setOriginLocation(?CustomerLocation $originLocation): void { $this->originLocation = $originLocation; }

    public function getTotalWeightKg(): ?float { return $this->totalWeightKg !== null ? (float) $this->totalWeightKg : null; }
    public function setTotalWeightKg(?float $v): void { $this->totalWeightKg = $v !== null ? (string) $v : null; }
    public function getTotalVolumeM3(): ?float { return $this->totalVolumeM3 !== null ? (float) $this->totalVolumeM3 : null; }
    public function setTotalVolumeM3(?float $v): void { $this->totalVolumeM3 = $v !== null ? (string) $v : null; }
    public function getTotalParcels(): ?int { return $this->totalParcels; }
    public function setTotalParcels(?int $v): void { $this->totalParcels = $v; }
    public function getTotalDistanceKm(): ?float { return $this->totalDistanceKm !== null ? (float) $this->totalDistanceKm : null; }
    public function setTotalDistanceKm(?float $v): void { $this->totalDistanceKm = $v !== null ? (string) $v : null; }
    public function getEstimatedDurationMinutes(): ?int { return $this->estimatedDurationMinutes; }
    public function setEstimatedDurationMinutes(?int $v): void { $this->estimatedDurationMinutes = $v; }

    /** @return array<string, mixed>|null */
    public function getAiAnalysis(): ?array { return $this->aiAnalysis; }
    /** @param array<string, mixed>|null $aiAnalysis */
    public function setAiAnalysis(?array $aiAnalysis): void { $this->aiAnalysis = $aiAnalysis; }

    public function isAutoReoptimize(): bool { return $this->autoReoptimize; }
    public function setAutoReoptimize(bool $autoReoptimize): void { $this->autoReoptimize = $autoReoptimize; }

    // ── Domain Logic ──

    public function start(): void
    {
        if ($this->status === RouteStatus::PLANNED) {
            $this->status = RouteStatus::ACTIVE;
            $this->startAt = new DateTimeImmutable();
        }
    }

    public function finish(): void
    {
        if ($this->status === RouteStatus::ACTIVE) {
            $this->status = RouteStatus::DONE;
            $this->endAt = new DateTimeImmutable();
        }
    }

    /**
     * Apply a RouteEvent to mutate aggregate state.
     * This is the single entry point for all state transitions.
     */
    public function apply(RouteEvent $event): void
    {
        match ($event->getEventType()) {
            RouteEventType::CREATED => $this->applyCreated($event),
            RouteEventType::STARTED => $this->start(),
            RouteEventType::COMPLETED => $this->finish(),
            RouteEventType::CANCELLED => $this->applyCancelled(),
            RouteEventType::OPTIMIZED,
            RouteEventType::REOPTIMIZED => $this->applyOptimized($event),
            RouteEventType::ASSIGNED => $this->applyAssigned($event),
            default => null, // Stop-level and metadata events are no-ops at Route level
        };
    }

    /**
     * Rebuild Route state from a sequence of events.
     *
     * @param list<RouteEvent> $events Ordered by occurredAt ASC
     */
    public static function rebuildFromEvents(string $name, array $events): self
    {
        $route = new self($name);

        foreach ($events as $event) {
            $route->apply($event);
        }

        return $route;
    }

    private function applyCreated(RouteEvent $event): void
    {
        // Route was already constructed with name; CREATED is the initial event.
    }

    private function applyCancelled(): void
    {
        $this->status = RouteStatus::CANCELLED;
    }

    private function applyOptimized(RouteEvent $event): void
    {
        $payload = $event->getPayload();
        if (isset($payload['distance_km'])) {
            $this->setTotalDistanceKm((float) $payload['distance_km']);
        }
        if (isset($payload['duration_minutes'])) {
            $this->setEstimatedDurationMinutes((int) $payload['duration_minutes']);
        }
    }

    private function applyAssigned(RouteEvent $event): void
    {
        // Driver and Vehicle assignment requires entity references.
        // When rebuilding from events, we can only store IDs — actual entity
        // resolution happens at the infrastructure layer.
    }
}
