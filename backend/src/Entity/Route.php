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
