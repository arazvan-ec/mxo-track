<?php

declare(strict_types=1);

namespace App\Prediction;

final readonly class DemandForecast
{
    /**
     * @param array{low: int, high: int} $confidenceInterval
     */
    public function __construct(
        public string $zoneId,
        public \DateTimeImmutable $date,
        public int $expectedShipments,
        public array $confidenceInterval,
    ) {
    }
}
