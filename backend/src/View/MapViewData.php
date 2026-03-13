<?php

declare(strict_types=1);

namespace App\View;

final class MapViewData
{
    /**
     * @param list<RouteViewData> $routes
     * @param array{lat: float, lng: float, address?: string}|null $origin
     * @param array<string, mixed>|null $globalMetrics
     */
    public function __construct(
        public readonly array $routes,
        public readonly MapViewOptions $options,
        public readonly ?array $origin = null,
        public readonly ?array $globalMetrics = null,
        public readonly ?string $mercureTopic = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'routes' => array_map(static fn(RouteViewData $r) => $r->toArray(), $this->routes),
            'origin' => $this->origin,
            'globalMetrics' => $this->globalMetrics,
            'mercureTopic' => $this->mercureTopic,
        ], static fn($v) => $v !== null);
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP);
    }
}
