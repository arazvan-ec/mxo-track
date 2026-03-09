<?php

declare(strict_types=1);

namespace App\Routing;

final readonly class Coordinate
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {
    }
}
