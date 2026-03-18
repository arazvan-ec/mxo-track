<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\MapView\Projection\MapProjectableEventInterface;

final readonly class DeviationDetected implements MapProjectableEventInterface
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

    public function getRoutePublicId(): string
    {
        return $this->routePublicId;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
