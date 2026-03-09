<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use App\Entity\Concerns\SoftDeleteTrait;
use App\Enum\RouteStatus;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: \App\Repository\RouteRepository::class)]
#[ORM\Table(name: 'route_plan')]
#[ORM\UniqueConstraint(name: 'uniq_route_public_id', columns: ['public_id'])]
#[ORM\Index(name: 'idx_route_deleted_at', columns: ['deleted_at'])]
#[ORM\HasLifecycleCallbacks]
class Route implements SoftDeletableInterface
{
    use PublicIdTrait;
    use SoftDeleteTrait;

    #[ORM\Column(length: 140)]
    #[Assert\NotBlank(message: 'El nombre de la ruta es obligatorio.')]
    #[Assert\Length(max: 140)]
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

    public function __construct(string $name)
    {
        $this->name = $name;
    }

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
}
