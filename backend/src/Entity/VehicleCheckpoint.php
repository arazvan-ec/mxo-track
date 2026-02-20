<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'vehicle_checkpoint')]
#[ORM\UniqueConstraint(name: 'uniq_vehicle_checkpoint_public_id', columns: ['public_id'])]
#[ORM\UniqueConstraint(name: 'uniq_vehicle_checkpoint_vehicle', columns: ['vehicle_id'])]
#[ORM\HasLifecycleCallbacks]
class VehicleCheckpoint
{
    use PublicIdTrait;

    #[ORM\OneToOne(targetEntity: Vehicle::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Vehicle $vehicle;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $lastDeviceTime = null;

    #[ORM\Column(nullable: true)]
    private ?int $lastTraccarPositionId = null;

    public function __construct(Vehicle $vehicle)
    {
        $this->vehicle = $vehicle;
    }

    public function getVehicle(): Vehicle
    {
        return $this->vehicle;
    }

    public function getLastDeviceTime(): ?DateTimeImmutable
    {
        return $this->lastDeviceTime;
    }

    public function setLastDeviceTime(?DateTimeImmutable $lastDeviceTime): void
    {
        $this->lastDeviceTime = $lastDeviceTime;
    }

    public function getLastTraccarPositionId(): ?int
    {
        return $this->lastTraccarPositionId;
    }

    public function setLastTraccarPositionId(?int $lastTraccarPositionId): void
    {
        $this->lastTraccarPositionId = $lastTraccarPositionId;
    }
}
