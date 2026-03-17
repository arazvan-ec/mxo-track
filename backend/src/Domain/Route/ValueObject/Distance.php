<?php

declare(strict_types=1);

namespace App\Domain\Route\ValueObject;

final readonly class Distance
{
    public function __construct(
        public float $km,
    ) {
        if ($km < 0.0) {
            throw new \InvalidArgumentException('Distance cannot be negative.');
        }
    }
}
