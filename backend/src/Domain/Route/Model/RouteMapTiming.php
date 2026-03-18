<?php

declare(strict_types=1);

namespace App\Domain\Route\Model;

final readonly class RouteMapTiming
{
    public function __construct(
        public ?int $drivingTimeMinutes = null,
        public ?int $deliveryTimeMinutes = null,
        public ?int $totalTimeMinutes = null,
    ) {}

    /** @return array<string, int> */
    public function toArray(): array
    {
        return array_filter([
            'drivingTimeMinutes' => $this->drivingTimeMinutes,
            'deliveryTimeMinutes' => $this->deliveryTimeMinutes,
            'totalTimeMinutes' => $this->totalTimeMinutes,
        ], static fn (mixed $v) => $v !== null);
    }
}
