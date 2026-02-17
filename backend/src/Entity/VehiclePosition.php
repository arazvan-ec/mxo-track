<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'vehicle_positions')]
#[ORM\UniqueConstraint(name: 'uniq_vehicle_pos_time', columns: ['vehicle_id', 'device_time'])]
class VehiclePosition
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Vehicle::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Vehicle $vehicle;

    #[ORM\Column(type: 'float')] private float $lat;
    #[ORM\Column(type: 'float')] private float $lng;
    #[ORM\Column(type: 'float')] private float $speed = 0;
    #[ORM\Column(type: 'float')] private float $course = 0;
    #[ORM\Column(type: 'float')] private float $accuracy = 0;
    #[ORM\Column] private DateTimeImmutable $deviceTime;
    #[ORM\Column] private DateTimeImmutable $serverTime;
    #[ORM\Column(nullable: true)] private ?int $traccarPositionId = null;

    public function __construct(Vehicle $vehicle, float $lat, float $lng, DateTimeImmutable $deviceTime, DateTimeImmutable $serverTime)
    {
        $this->id = Uuid::v7();
        $this->vehicle = $vehicle;
        $this->lat = $lat;
        $this->lng = $lng;
        $this->deviceTime = $deviceTime;
        $this->serverTime = $serverTime;
    }

    public function getVehicle(): Vehicle { return $this->vehicle; }
    public function getLat(): float { return $this->lat; }
    public function getLng(): float { return $this->lng; }
    public function getSpeed(): float { return $this->speed; }
    public function getCourse(): float { return $this->course; }
    public function getAccuracy(): float { return $this->accuracy; }
    public function getDeviceTime(): DateTimeImmutable { return $this->deviceTime; }
    public function getServerTime(): DateTimeImmutable { return $this->serverTime; }
}
