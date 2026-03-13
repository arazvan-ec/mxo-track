<?php

declare(strict_types=1);

namespace App\View;

final class RouteViewData
{
    /**
     * @param list<StopViewData> $stops
     * @param array<string, mixed>|null $metrics
     * @param array<string, mixed>|null $timing
     * @param array<string, mixed>|null $validation
     * @param array<int, array<string, mixed>>|null $originalStops
     */
    public function __construct(
        public readonly string $publicId,
        public readonly string $name,
        public readonly string $color,
        public readonly ?string $vehicleName,
        public readonly ?string $driverName,
        public readonly ?string $status,
        public readonly array $stops,
        public readonly ?string $polyline = null,
        public readonly ?array $metrics = null,
        public readonly ?array $timing = null,
        public readonly ?array $validation = null,
        public readonly ?array $originalStops = null,
        public readonly ?string $comparisonPolyline = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'publicId' => $this->publicId,
            'name' => $this->name,
            'color' => $this->color,
            'vehicleName' => $this->vehicleName,
            'driverName' => $this->driverName,
            'status' => $this->status,
            'stops' => array_map(static fn(StopViewData $s) => $s->toArray(), $this->stops),
            'polyline' => $this->polyline,
            'metrics' => $this->metrics,
            'timing' => $this->timing,
            'validation' => $this->validation,
            'originalStops' => $this->originalStops,
            'comparisonPolyline' => $this->comparisonPolyline,
        ], static fn($v) => $v !== null);
    }
}
