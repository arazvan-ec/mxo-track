<?php

declare(strict_types=1);

namespace App\Dto;

final readonly class DeviationCheckResult
{
    public function __construct(
        public bool $isDeviated,
        public float $distanceMeters,
        public float $thresholdMeters,
    ) {}
}
