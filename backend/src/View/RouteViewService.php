<?php

declare(strict_types=1);

namespace App\View;

use App\Entity\Route;
use App\Domain\Route\Model\RouteSnapshot;
use App\Domain\Route\Repository\RouteSnapshotRepositoryInterface;

/**
 * Reads from RouteSnapshot (persisted) and produces MapViewData DTOs.
 * Applies role-based filtering. No OSRM calls — all data from snapshot.
 */
final class RouteViewService
{
    private const ROUTE_COLORS = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#14b8a6'];

    public function __construct(
        private readonly RouteSnapshotRepositoryInterface $snapshotRepo,
    ) {}

    public function buildSingleRouteView(Route $route, string $role, ?MapViewOptions $options = null): MapViewData
    {
        $options ??= new MapViewOptions();
        $snapshot = $this->snapshotRepo->findByRoute($route);

        $routeView = $this->buildRouteViewData($route, $snapshot, $role, $options, 0);

        $mercureTopic = $this->buildMercureTopic($route, $role);

        return new MapViewData(
            routes: [$routeView],
            options: $options,
            origin: $this->extractOrigin($snapshot),
            mercureTopic: $mercureTopic,
        );
    }

    /**
     * @param list<Route> $routes
     */
    public function buildMultiRouteView(array $routes, string $role, ?MapViewOptions $options = null): MapViewData
    {
        $options ??= new MapViewOptions();
        $routeViews = [];
        $totalDistanceBefore = 0.0;
        $totalDistanceAfter = 0.0;
        $origin = null;

        foreach ($routes as $index => $route) {
            $snapshot = $this->snapshotRepo->findByRoute($route);
            $routeViews[] = $this->buildRouteViewData($route, $snapshot, $role, $options, $index);

            if ($snapshot !== null) {
                $totalDistanceBefore += $snapshot->getDistanceBeforeKm() ?? 0.0;
                $totalDistanceAfter += $snapshot->getDistanceAfterKm() ?? 0.0;
            }

            if ($origin === null) {
                $origin = $this->extractOrigin($snapshot);
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
            routes: $routeViews,
            options: $options,
            origin: $origin,
            globalMetrics: $globalMetrics,
        );
    }

    private function buildRouteViewData(
        Route $route,
        ?RouteSnapshot $snapshot,
        string $role,
        MapViewOptions $options,
        int $colorIndex,
    ): RouteViewData {
        $color = self::ROUTE_COLORS[$colorIndex % \count(self::ROUTE_COLORS)];
        $stops = $this->buildStopViews($snapshot);

        // Build full view first, then filter by role
        $polyline = $options->showPolylines ? $snapshot?->getPolyline() : null;

        $metrics = null;
        $timing = null;
        $validation = null;
        $originalStops = null;
        $comparisonPolyline = null;

        if ($role === 'ROLE_ADMIN') {
            if ($options->showOptimizationMetrics && $snapshot !== null) {
                $metrics = [
                    'distanceBeforeKm' => $snapshot->getDistanceBeforeKm(),
                    'distanceAfterKm' => $snapshot->getDistanceAfterKm(),
                    'savingsPercent' => $snapshot->getSavingsPercent(),
                ];
            }

            if ($options->showTimingBreakdown && $snapshot !== null) {
                $timing = [
                    'drivingTimeMinutes' => $snapshot->getDrivingTimeMinutes(),
                    'deliveryTimeMinutes' => $snapshot->getDeliveryTimeMinutes(),
                    'totalTimeMinutes' => $snapshot->getTotalTimeMinutes(),
                ];
            }

            if ($options->showCapacityValidation && $snapshot !== null) {
                $validation = $snapshot->getCapacityValidation();
            }

            if ($options->showOriginalOrder && $snapshot !== null) {
                $originalStops = $snapshot->getOriginalStopOrder();
            }

            if ($options->comparisonMode === 'planned_vs_actual' && $snapshot !== null) {
                $comparisonPolyline = $snapshot->getActualPolyline();
            }
        }

        try {
            $publicId = $route->getPublicIdString();
        } catch (\Error) {
            $publicId = '';
        }

        return new RouteViewData(
            publicId: $publicId,
            name: $route->getName(),
            color: $color,
            vehicleName: $route->getVehicle()?->getName(),
            driverName: $route->getDriver()?->getFullName(),
            status: $route->getStatus()->value,
            stops: $stops,
            polyline: $polyline,
            metrics: $metrics,
            timing: $timing,
            validation: $validation,
            originalStops: $originalStops,
            comparisonPolyline: $comparisonPolyline,
        );
    }

    /**
     * @return list<StopViewData>
     */
    private function buildStopViews(?RouteSnapshot $snapshot): array
    {
        if ($snapshot === null) {
            return [];
        }

        $stopStates = $snapshot->getStopStates();
        if ($stopStates === null) {
            return [];
        }

        $etas = $snapshot->getEtas() ?? [];

        $views = [];
        foreach ($stopStates as $state) {
            $publicId = $state['publicId'] ?? null;
            $stopEta = $publicId !== null ? ($etas[$publicId] ?? null) : null;

            $views[] = new StopViewData(
                sequence: $state['sequence'] ?? 0,
                address: $state['address'] ?? '',
                recipientName: $state['recipientName'] ?? null,
                recipientPhone: $state['recipientPhone'] ?? null,
                lat: $state['lat'] ?? null,
                lng: $state['lng'] ?? null,
                status: $state['status'] ?? 'PENDING',
                isOrigin: $state['isOrigin'] ?? false,
                deliveredAt: $state['deliveredAt'] ?? null,
                exceptionCode: $state['exceptionCode'] ?? null,
                exceptionNotes: $state['exceptionNotes'] ?? null,
                etaMinutes: $stopEta['minutes'] ?? null,
                etaTime: $stopEta !== null ? (new \DateTimeImmutable($stopEta['eta']))->format('H:i') : null,
                etaDistanceKm: $stopEta['distance_km'] ?? null,
            );
        }

        return $views;
    }

    /**
     * @return array{lat: float, lng: float, address: string}|null
     */
    private function extractOrigin(?RouteSnapshot $snapshot): ?array
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
                    'lat' => $state['lat'] ?? 0.0,
                    'lng' => $state['lng'] ?? 0.0,
                    'address' => $state['address'] ?? '',
                ];
            }
        }

        return null;
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
