<?php

declare(strict_types=1);

namespace App\View;

use App\Domain\Route\Model\RouteMapView;
use App\Domain\Route\Model\StopMapView;

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

    public static function fromMapView(RouteMapView $view): self
    {
        $stops = array_map(static fn (StopMapView $s) => new StopViewData(
            sequence: $s->sequence,
            address: $s->address,
            recipientName: $s->recipientName,
            recipientPhone: $s->recipientPhone,
            lat: $s->lat,
            lng: $s->lng,
            status: $s->status,
            isOrigin: $s->isOrigin,
            deliveredAt: $s->deliveredAt,
            exceptionCode: $s->exceptionCode,
            exceptionNotes: $s->exceptionNotes,
            etaMinutes: $s->etaMinutes,
            etaTime: $s->etaTime,
            etaDistanceKm: $s->etaDistanceKm,
        ), $view->stops);

        $originalStops = $view->originalStops !== null
            ? array_map(static fn (StopMapView $s) => $s->toArray(), $view->originalStops)
            : null;

        return new self(
            publicId: $view->publicId,
            name: $view->name,
            color: $view->color,
            vehicleName: $view->vehicleName,
            driverName: $view->driverName,
            status: $view->status,
            stops: $stops,
            polyline: $view->polyline,
            metrics: $view->metrics?->toArray(),
            timing: $view->timing?->toArray(),
            validation: $view->validation,
            originalStops: $originalStops,
            comparisonPolyline: $view->comparisonPolyline,
        );
    }

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
