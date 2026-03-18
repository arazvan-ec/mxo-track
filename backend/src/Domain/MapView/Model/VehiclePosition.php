<?php

declare(strict_types=1);

namespace App\Domain\MapView\Model;

final readonly class VehiclePosition
{
    public function __construct(
        public string $vehiclePublicId,
        public float $lat,
        public float $lng,
        public ?float $speed,
        public ?float $course,
        public \DateTimeImmutable $deviceTime,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'vehiclePublicId' => $this->vehiclePublicId,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'speed' => $this->speed,
            'course' => $this->course,
            'deviceTime' => $this->deviceTime->format(\DateTimeInterface::ATOM),
        ];
    }
}
