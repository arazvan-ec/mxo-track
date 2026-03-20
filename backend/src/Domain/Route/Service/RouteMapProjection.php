<?php

declare(strict_types=1);

namespace App\Domain\Route\Service;

use App\Domain\Route\Model\RouteMapMetrics;
use App\Domain\Route\Model\RouteMapOptions;
use App\Domain\Route\Model\RouteMapTiming;
use App\Domain\Route\Model\RouteMapView;
use App\Domain\Route\Model\RouteSnapshot;
use App\Domain\Route\Model\StopMapView;
use App\Domain\Route\Repository\RouteSnapshotRepositoryInterface;
use App\Domain\Route\Model\Route;

/**
 * Projects Route + RouteSnapshot into RouteMapView Value Objects.
 *
 * Single source of truth for map data across all endpoints:
 * FleetOverview, RoutePlanning, RouteView, etc.
 */
final readonly class RouteMapProjection
{
    private const ROUTE_COLORS = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#14b8a6'];

    public function __construct(
        private RouteSnapshotRepositoryInterface $snapshotRepo,
    ) {}

    public function projectRoute(
        Route $route,
        RouteMapOptions $options,
        int $colorIndex = 0,
        ?RouteSnapshot $snapshot = null,
    ): RouteMapView {
        $snapshot ??= $this->snapshotRepo->findByRoute($route);

        return $this->buildView($route, $snapshot, $options, $colorIndex);
    }

    /**
     * Projects multiple routes efficiently (single query for snapshots).
     *
     * @param list<Route> $routes
     * @return list<RouteMapView>
     */
    public function projectRoutes(array $routes, RouteMapOptions $options): array
    {
        $snapshotMap = $this->snapshotRepo->findByRoutes($routes);
        $views = [];

        foreach ($routes as $index => $route) {
            $snapshot = $snapshotMap[$route->getId()] ?? null;
            $views[] = $this->buildView($route, $snapshot, $options, $index);
        }

        return $views;
    }

    /**
     * Extract origin coordinates from snapshot stop states.
     *
     * @return array{lat: float, lng: float, address: string}|null
     */
    public function extractOrigin(?RouteSnapshot $snapshot): ?array
    {
        if ($snapshot === null) {
            return null;
        }

        $stopStates = $snapshot->getStopStates();
        if ($stopStates === null) {
            return null;
        }

        foreach ($stopStates as $state) {
            if ($state['isOrigin'] ?? false) {
                return [
                    'lat' => (float) ($state['lat'] ?? 0.0),
                    'lng' => (float) ($state['lng'] ?? 0.0),
                    'address' => (string) ($state['address'] ?? ''),
                ];
            }
        }

        return null;
    }

    private function buildView(
        Route $route,
        ?RouteSnapshot $snapshot,
        RouteMapOptions $options,
        int $colorIndex,
    ): RouteMapView {
        $color = self::ROUTE_COLORS[$colorIndex % \count(self::ROUTE_COLORS)];
        $stops = $this->buildStops($snapshot, $options);

        try {
            $publicId = $route->getPublicIdString();
        } catch (\Error) {
            $publicId = '';
        }

        return new RouteMapView(
            publicId: $publicId,
            name: $route->getName(),
            color: $color,
            polyline: $snapshot?->getPolyline(),
            stops: $stops,
            status: $route->getStatus()->value,
            vehicleName: $route->getVehicle()?->getName(),
            driverName: $route->getDriver()?->getName() ?? $route->getDriver()?->getEmail(),
            metrics: $this->buildMetrics($snapshot, $options),
            timing: $this->buildTiming($snapshot, $options),
            validation: $options->includeValidation ? $snapshot?->getCapacityValidation() : null,
            comparisonPolyline: $options->includeComparisonPolyline ? $snapshot?->getActualPolyline() : null,
            originalStops: $options->includeOriginalStops ? $this->buildOriginalStops($snapshot) : null,
        );
    }

    /**
     * @return list<StopMapView>
     */
    private function buildStops(?RouteSnapshot $snapshot, RouteMapOptions $options): array
    {
        if ($snapshot === null) {
            return [];
        }

        $stopStates = $snapshot->getStopStates();
        if ($stopStates === null) {
            return [];
        }

        $etas = ($options->includeEtas ? $snapshot->getEtas() : null) ?? [];

        $views = [];
        foreach ($stopStates as $state) {
            $publicId = $state['publicId'] ?? null;
            $etaData = $publicId !== null ? ($etas[$publicId] ?? null) : null;
            $views[] = StopMapView::fromSnapshotState($state, $etaData);
        }

        return $views;
    }

    private function buildMetrics(?RouteSnapshot $snapshot, RouteMapOptions $options): ?RouteMapMetrics
    {
        if (!$options->includeMetrics || $snapshot === null) {
            return null;
        }

        return new RouteMapMetrics(
            distanceBeforeKm: $snapshot->getDistanceBeforeKm(),
            distanceAfterKm: $snapshot->getDistanceAfterKm(),
            savingsPercent: $snapshot->getSavingsPercent(),
        );
    }

    private function buildTiming(?RouteSnapshot $snapshot, RouteMapOptions $options): ?RouteMapTiming
    {
        if (!$options->includeTiming || $snapshot === null) {
            return null;
        }

        return new RouteMapTiming(
            drivingTimeMinutes: $snapshot->getDrivingTimeMinutes(),
            deliveryTimeMinutes: $snapshot->getDeliveryTimeMinutes(),
            totalTimeMinutes: $snapshot->getTotalTimeMinutes(),
        );
    }

    /**
     * @return list<StopMapView>|null
     */
    private function buildOriginalStops(?RouteSnapshot $snapshot): ?array
    {
        if ($snapshot === null) {
            return null;
        }

        $original = $snapshot->getOriginalStopOrder();
        if ($original === null) {
            return null;
        }

        return array_map(
            static fn (array $state) => StopMapView::fromSnapshotState($state),
            $original,
        );
    }
}
