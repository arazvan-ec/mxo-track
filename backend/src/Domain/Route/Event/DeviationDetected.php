<?php

declare(strict_types=1);

namespace App\Domain\Route\Event;

final readonly class DeviationDetected
{
    public function __construct(
        public string $routePublicId,
        public string $vehiclePublicId,
        public float $latitude,
        public float $longitude,
        public float $distanceMeters,
        public float $thresholdMeters,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}
}
