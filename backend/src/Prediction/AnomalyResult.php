<?php

declare(strict_types=1);

namespace App\Prediction;

final readonly class AnomalyResult
{
    public function __construct(
        public string $entityType,
        public string $entityId,
        public string $anomalyType,
        public float $severity,
        public string $description,
        public \DateTimeImmutable $detectedAt,
    ) {
    }
}
