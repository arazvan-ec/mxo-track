<?php

declare(strict_types=1);

namespace App\View;

use App\Domain\Route\Model\RouteMapOptions;
use App\Domain\Route\Repository\RouteSnapshotRepositoryInterface;
use App\Domain\Route\Service\RouteMapProjection;
use App\Domain\Route\Model\Route;

/**
 * Reads from RouteSnapshot (persisted) and produces MapViewData DTOs.
 * Applies role-based filtering. No OSRM calls — all data from snapshot.
 *
 * Delegates route projection to RouteMapProjection domain service.
 */
final class RouteViewService
{
    public function __construct(
        private readonly RouteMapProjection $projection,
        private readonly RouteSnapshotRepositoryInterface $snapshotRepo,
    ) {}

    public function buildSingleRouteView(Route $route, string $role, ?MapViewOptions $mapOptions = null): MapViewData
    {
        $mapOptions ??= new MapViewOptions();
        $options = $this->buildProjectionOptions($role, $mapOptions);
        $snapshot = $this->snapshotRepo->findByRoute($route);

        $view = $this->projection->projectRoute($route, $options, 0, $snapshot);

        $routeViewData = RouteViewData::fromMapView($view);

        $mercureTopic = $this->buildMercureTopic($route, $role);

        return new MapViewData(
            routes: [$routeViewData],
            options: $mapOptions,
            origin: $this->projection->extractOrigin($snapshot),
            mercureTopic: $mercureTopic,
        );
    }

    /**
     * @param list<Route> $routes
     */
    public function buildMultiRouteView(array $routes, string $role, ?MapViewOptions $mapOptions = null): MapViewData
    {
        $mapOptions ??= new MapViewOptions();
        $options = $this->buildProjectionOptions($role, $mapOptions);
        $views = $this->projection->projectRoutes($routes, $options);

        $routeViewDatas = array_map(
            static fn ($view) => RouteViewData::fromMapView($view),
            $views,
        );

        // Aggregate global metrics
        $totalDistanceBefore = 0.0;
        $totalDistanceAfter = 0.0;
        $origin = null;

        foreach ($views as $view) {
            if ($view->metrics !== null) {
                $totalDistanceBefore += $view->metrics->distanceBeforeKm ?? 0.0;
                $totalDistanceAfter += $view->metrics->distanceAfterKm ?? 0.0;
            }
            if ($origin === null && \count($view->stops) > 0) {
                foreach ($view->stops as $stop) {
                    if ($stop->isOrigin && $stop->lat !== null && $stop->lng !== null) {
                        $origin = ['lat' => $stop->lat, 'lng' => $stop->lng, 'address' => $stop->address];
                        break;
                    }
                }
            }
        }

        $globalMetrics = null;
        if ($totalDistanceBefore > 0 || $totalDistanceAfter > 0) {
            $savings = $totalDistanceBefore > 0
                ? round((1 - $totalDistanceAfter / $totalDistanceBefore) * 100, 1)
                : 0.0;

            $globalMetrics = [
                'totalDistanceBeforeKm' => $totalDistanceBefore,
                'totalDistanceAfterKm' => $totalDistanceAfter,
                'totalSavingsPercent' => $savings,
                'routeCount' => \count($routes),
            ];
        }

        return new MapViewData(
            routes: $routeViewDatas,
            options: $mapOptions,
            origin: $origin,
            globalMetrics: $globalMetrics,
        );
    }

    private function buildProjectionOptions(string $role, MapViewOptions $mapOptions): RouteMapOptions
    {
        $isAdmin = $role === 'ROLE_ADMIN';

        return new RouteMapOptions(
            includeMetrics: $isAdmin && $mapOptions->showOptimizationMetrics,
            includeTiming: $isAdmin && $mapOptions->showTimingBreakdown,
            includeValidation: $isAdmin && $mapOptions->showCapacityValidation,
            includeOriginalStops: $isAdmin && $mapOptions->showOriginalOrder,
            includeComparisonPolyline: $isAdmin && $mapOptions->comparisonMode === 'planned_vs_actual',
            includeEtas: true,
        );
    }

    private function buildMercureTopic(Route $route, string $role): string
    {
        try {
            $publicId = $route->getPublicIdString();
        } catch (\Error) {
            $publicId = 'unknown';
        }

        $roleSuffix = strtolower(str_replace('ROLE_', '', $role));

        return sprintf('/routes/%s/view/%s', $publicId, $roleSuffix);
    }
}
