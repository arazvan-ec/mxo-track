<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'vehicle_last_position')]
class VehicleLastPosition
{
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: Vehicle::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Vehicle $vehicle;

    #[ORM\Column(type: 'float')] private float $lat;
    #[ORM\Column(type: 'float')] private float $lng;
    #[ORM\Column(type: 'float')] private float $speed = 0;
    #[ORM\Column(type: 'float')] private float $course = 0;
    #[ORM\Column(type: 'float')] private float $accuracy = 0;
    #[ORM\Column] private DateTimeImmutable $deviceTime;
    #[ORM\Column] private DateTimeImmutable $serverTime;

    public static function fromTelemetry(
        Vehicle $vehicle,
        float $lat,
        float $lng,
        float $speed,
        float $course,
        float $accuracy,
        DateTimeImmutable $deviceTime,
        DateTimeImmutable $serverTime,
    ): self {
        $self = new self();
        $self->vehicle = $vehicle;
        $self->lat = $lat;
        $self->lng = $lng;
        $self->speed = $speed;
        $self->course = $course;
        $self->accuracy = $accuracy;
        $self->deviceTime = $deviceTime;
        $self->serverTime = $serverTime;

        return $self;
    }

    public function refresh(
        float $lat,
        float $lng,
        float $speed,
        float $course,
        float $accuracy,
        DateTimeImmutable $deviceTime,
        DateTimeImmutable $serverTime,
    ): void {
        $this->lat = $lat;
        $this->lng = $lng;
        $this->speed = $speed;
        $this->course = $course;
        $this->accuracy = $accuracy;
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
