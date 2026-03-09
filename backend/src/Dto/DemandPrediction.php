<?php

declare(strict_types=1);

namespace App\Dto;

final readonly class DemandPrediction
{
    public function __construct(
        public \DateTimeImmutable $date,
        public string $dayOfWeek,
        public int $predictedDeliveries,
        public int $minDeliveries,
        public int $maxDeliveries,
        public int $recommendedVehicles,
        public string $confidence,
    ) {}
}
