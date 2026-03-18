<?php

declare(strict_types=1);

namespace App\Domain\Route\Model;

final readonly class RouteMapView
{
    /**
     * @param list<StopMapView> $stops
     * @param list<StopMapView>|null $originalStops
     */
    public function __construct(
        public string $publicId,
        public string $name,
        public string $color,
        public ?string $polyline,
        public array $stops,
        public ?string $status = null,
        public ?string $vehicleName = null,
        public ?string $driverName = null,
        public ?RouteMapMetrics $metrics = null,
        public ?RouteMapTiming $timing = null,
        public ?array $validation = null,
        public ?string $comparisonPolyline = null,
        public ?array $originalStops = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'publicId' => $this->publicId,
            'name' => $this->name,
            'color' => $this->color,
            'polyline' => $this->polyline,
            'stops' => array_map(static fn (StopMapView $s) => $s->toArray(), $this->stops),
        ];

        if ($this->status !== null) {
            $data['status'] = $this->status;
        }
        if ($this->vehicleName !== null) {
            $data['vehicleName'] = $this->vehicleName;
        }
        if ($this->driverName !== null) {
            $data['driverName'] = $this->driverName;
        }
        if ($this->metrics !== null) {
            $data['metrics'] = $this->metrics->toArray();
        }
        if ($this->timing !== null) {
            $data['timing'] = $this->timing->toArray();
        }
        if ($this->validation !== null) {
            $data['validation'] = $this->validation;
        }
        if ($this->comparisonPolyline !== null) {
            $data['comparisonPolyline'] = $this->comparisonPolyline;
        }
        if ($this->originalStops !== null) {
            $data['originalStops'] = array_map(static fn (StopMapView $s) => $s->toArray(), $this->originalStops);
        }

        return $data;
    }
}
