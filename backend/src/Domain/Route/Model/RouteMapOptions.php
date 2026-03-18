<?php

declare(strict_types=1);

namespace App\Domain\Route\Model;

final readonly class RouteMapOptions
{
    public function __construct(
        public bool $includeMetrics = false,
        public bool $includeTiming = false,
        public bool $includeValidation = false,
        public bool $includeOriginalStops = false,
        public bool $includeComparisonPolyline = false,
        public bool $includeEtas = false,
    ) {}

    public static function full(): self
    {
        return new self(
            includeMetrics: true,
            includeTiming: true,
            includeValidation: true,
            includeOriginalStops: true,
            includeComparisonPolyline: true,
            includeEtas: true,
        );
    }

    public static function minimal(): self
    {
        return new self();
    }
}
