<?php

declare(strict_types=1);

namespace App\Domain\Event;

final readonly class VehiclePositionReceived
{
    public function __construct(
        public string $vehiclePublicId,
        public float $latitude,
        public float $longitude,
        public ?float $speed,
        public ?float $course,
        public \DateTimeImmutable $deviceTime,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}
}
