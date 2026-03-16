<?php

declare(strict_types=1);

namespace App\Domain\Route\ValueObject;

final readonly class Coordinate
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {
        if ($latitude < -90.0 || $latitude > 90.0) {
            throw new \InvalidArgumentException(sprintf('Latitude must be between -90 and 90, got %f.', $latitude));
        }
        if ($longitude < -180.0 || $longitude > 180.0) {
            throw new \InvalidArgumentException(sprintf('Longitude must be between -180 and 180, got %f.', $longitude));
        }
    }

    public function equals(self $other): bool
    {
        return $this->latitude === $other->latitude && $this->longitude === $other->longitude;
    }
}
