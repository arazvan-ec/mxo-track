<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use App\Enum\RouteStatus;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\RouteRepository::class)]
#[ORM\Table(name: 'route_plan')]
#[ORM\UniqueConstraint(name: 'uniq_route_public_id', columns: ['public_id'])]
#[ORM\HasLifecycleCallbacks]
class Route
{
    use PublicIdTrait;

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

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getStatus(): RouteStatus { return $this->status; }
    public function getVehicle(): ?Vehicle { return $this->vehicle; }

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
