<?php

declare(strict_types=1);

namespace App\Prediction;

final readonly class EtaPrediction
{
    public function __construct(
        public string $routeStopId,
        public \DateTimeImmutable $estimatedArrival,
        public float $confidenceScore,
        public string $modelVersion,
    ) {
    }
}
