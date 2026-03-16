<?php

declare(strict_types=1);

namespace App\Infrastructure\Route\Doctrine\Entity;

use App\Domain\Route\Model\Route;
use App\Domain\Route\ValueObject\Capacity;
use App\Domain\Route\ValueObject\Distance;
use App\Domain\Route\ValueObject\RouteId;
use App\Entity\Customer;
use App\Entity\CustomerLocation;
use App\Entity\SoftDeletableInterface;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Enum\RouteStatus;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'route_plan')]
#[ORM\UniqueConstraint(name: 'uniq_route_public_id', columns: ['public_id'])]
#[ORM\Index(name: 'idx_route_deleted_at', columns: ['deleted_at'])]
#[ORM\HasLifecycleCallbacks]
class RouteEntity implements SoftDeletableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?string $id = null;

    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $publicId;

    #[ORM\Column(length: 140)]
    private string $name;

    #[ORM\Column(length: 20, enumType: RouteStatus::class)]
    private RouteStatus $status = RouteStatus::PLANNED;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'driver_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $driver = null;

    #[ORM\ManyToOne(targetEntity: Vehicle::class)]
    #[ORM\JoinColumn(name: 'vehicle_id', nullable: true, onDelete: 'SET NULL')]
    private ?Vehicle $vehicle = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $startAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $endAt = null;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'customer_id', nullable: true, onDelete: 'SET NULL')]
    private ?Customer $customer = null;

    #[ORM\ManyToOne(targetEntity: CustomerLocation::class)]
    #[ORM\JoinColumn(name: 'origin_location_id', nullable: true, onDelete: 'SET NULL')]
    private ?CustomerLocation $originLocation = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $totalWeightKg = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 4, nullable: true)]
    private ?string $totalVolumeM3 = null;

    #[ORM\Column(nullable: true)]
    private ?int $totalParcels = null;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, nullable: true)]
    private ?string $totalDistanceKm = null;

    #[ORM\Column(nullable: true)]
    private ?int $estimatedDurationMinutes = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $aiAnalysis = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $autoReoptimize = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $deletedAt = null;

    private function __construct()
    {
    }

    #[ORM\PrePersist]
    public function initializePublicId(): void
    {
        $this->publicId ??= new Ulid();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getPublicId(): Ulid
    {
        return $this->publicId;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function softDelete(): void
    {
        $this->deletedAt = new DateTimeImmutable();
    }

    // ── Domain ↔ Doctrine Mapping ──

    public function toDomain(): Route
    {
        $capacity = null;
        $weightKg = $this->totalWeightKg !== null ? (float) $this->totalWeightKg : null;
        $volumeM3 = $this->totalVolumeM3 !== null ? (float) $this->totalVolumeM3 : null;
        if ($weightKg !== null || $volumeM3 !== null || $this->totalParcels !== null) {
            $capacity = new Capacity($weightKg ?? 0.0, $volumeM3 ?? 0.0, $this->totalParcels ?? 0);
        }

        $distance = $this->totalDistanceKm !== null
            ? new Distance((float) $this->totalDistanceKm)
            : null;

        return Route::reconstitute(
            id: new RouteId((string) $this->publicId),
            name: $this->name,
            status: $this->status,
            driverId: $this->driver?->getId() !== null ? (int) $this->driver->getId() : null,
            vehicleId: $this->vehicle?->getId() !== null ? (int) $this->vehicle->getId() : null,
            customerId: $this->customer?->getId() !== null ? (int) $this->customer->getId() : null,
            originLocationId: $this->originLocation?->getId() !== null ? (int) $this->originLocation->getId() : null,
            capacity: $capacity,
            totalDistance: $distance,
            estimatedDurationMinutes: $this->estimatedDurationMinutes,
            aiAnalysis: $this->aiAnalysis,
            autoReoptimize: $this->autoReoptimize,
            startAt: $this->startAt,
            endAt: $this->endAt,
            deletedAt: $this->deletedAt,
        );
    }

    public static function fromDomain(Route $route): self
    {
        $entity = new self();
        $entity->publicId = Ulid::fromString((string) $route->id());
        $entity->updateFromDomain($route);

        return $entity;
    }

    public function updateFromDomain(Route $route): void
    {
        $this->name = $route->name();
        $this->status = $route->status();
        $this->startAt = $route->startAt();
        $this->endAt = $route->endAt();
        $this->estimatedDurationMinutes = $route->estimatedDurationMinutes();
        $this->aiAnalysis = $route->aiAnalysis();
        $this->autoReoptimize = $route->autoReoptimize();
        $this->deletedAt = $route->deletedAt();

        $capacity = $route->capacity();
        $this->totalWeightKg = $capacity !== null ? (string) $capacity->weightKg : null;
        $this->totalVolumeM3 = $capacity !== null ? (string) $capacity->volumeM3 : null;
        $this->totalParcels = $capacity?->parcels;

        $distance = $route->totalDistance();
        $this->totalDistanceKm = $distance !== null ? (string) $distance->km : null;

        // Note: driver, vehicle, customer, originLocation are resolved by the repository
        // using the int IDs from the domain model. They cannot be set here without EM references.
    }

    // ── Accessors for repository use ──

    public function setDriver(?User $driver): void
    {
        $this->driver = $driver;
    }

    public function setVehicle(?Vehicle $vehicle): void
    {
        $this->vehicle = $vehicle;
    }

    public function setCustomer(?Customer $customer): void
    {
        $this->customer = $customer;
    }

    public function setOriginLocation(?CustomerLocation $originLocation): void
    {
        $this->originLocation = $originLocation;
    }
}
