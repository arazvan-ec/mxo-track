<?php

declare(strict_types=1);

namespace App\Domain\Route\Model;

final readonly class RouteMapMetrics
{
    public function __construct(
        public ?float $distanceBeforeKm = null,
        public ?float $distanceAfterKm = null,
        public ?float $savingsPercent = null,
    ) {}

    /** @return array<string, float> */
    public function toArray(): array
    {
        return array_filter([
            'distanceBeforeKm' => $this->distanceBeforeKm,
            'distanceAfterKm' => $this->distanceAfterKm,
            'savingsPercent' => $this->savingsPercent,
        ], static fn (mixed $v) => $v !== null);
    }
}
