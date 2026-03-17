<?php

declare(strict_types=1);

namespace App\Domain\Route\ValueObject;

final readonly class Capacity
{
    public function __construct(
        public float $weightKg,
        public float $volumeM3,
        public int $parcels,
    ) {
        if ($weightKg < 0.0) {
            throw new \InvalidArgumentException('Weight cannot be negative.');
        }
        if ($volumeM3 < 0.0) {
            throw new \InvalidArgumentException('Volume cannot be negative.');
        }
        if ($parcels < 0) {
            throw new \InvalidArgumentException('Parcels cannot be negative.');
        }
    }

    public function add(self $other): self
    {
        return new self(
            $this->weightKg + $other->weightKg,
            $this->volumeM3 + $other->volumeM3,
            $this->parcels + $other->parcels,
        );
    }
}
