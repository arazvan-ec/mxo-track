<?php

declare(strict_types=1);

namespace App\RouteOptimization;

/**
 * A single step in an optimized route (job delivery, start, or end).
 */
final readonly class OptimizedStep
{
    public function __construct(
        public int|string $jobId,
        public string $type,
        public int $arrivalSeconds = 0,
        public int $serviceSeconds = 0,
    ) {
    }
}
