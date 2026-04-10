<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Route\Repository\RouteStopRepositoryInterface;
use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteStop;
use App\Domain\Shipment\Model\Shipment;
use App\Enum\OptimizationStepCategory;
use App\Enum\RouteStopStatus;
use App\Enum\VehicleSkill;
use App\RouteOptimization\OptimizableJob;
use App\RouteOptimization\OptimizableVehicle;
use App\RouteOptimization\RouteOptimizerInterface;
use App\Routing\Coordinate;
use App\Routing\RoutingEngineInterface;

/**
 * Re-optimizes the stop order of an existing route.
 * Uses RouteOptimizerInterface for stop sequencing and RoutingEngineInterface
 * for real road distances and durations.
 */
final class RouteOptimizationService
{
    public function __construct(
        private readonly RouteStopRepositoryInterface $stopRepo,
        private readonly RouteOptimizerInterface $routeOptimizer,
        private readonly RoutingEngineInterface $routingEngine,
        private readonly OptimizationLogger $optimizationLogger,
    ) {
    }

    /**
     * Optimizes the stop order of a route using VROOM + OSRM.
     *
     * @return array{
     *     optimized: list<array{stop: RouteStop, newSequence: int}>,
     *     distanceBefore: float,
     *     distanceAfter: float,
     *     durationMinutes: int,
     * }
     */
    public function optimizeStopOrder(Route $route): array
    {
        $stops = $this->getStopsForRoute($route);

        $originStop = null;
        $deliveryStops = [];

        foreach ($stops as $stop) {
            if ($stop->isOrigin()) {
                $originStop = $stop;
            } else {
                $deliveryStops[] = $stop;
            }
        }

        $distanceBefore = $this->calculateTotalRoadDistance($stops);

        $this->optimizationLogger->logStep(
            OptimizationStepCategory::DISTANCE_CALCULATION,
            sprintf('Distancia original: %.2fkm (%d paradas)', $distanceBefore, \count($deliveryStops)),
            ['distanceBeforeKm' => round($distanceBefore, 2), 'deliveryStopCount' => \count($deliveryStops)],
        );

        if (\count($deliveryStops) < 2) {
            $this->optimizationLogger->logStep(
                OptimizationStepCategory::OPTIMIZER_CALL,
                'Menos de 2 paradas, no se requiere optimizacion',
            );

            return $this->buildResult($originStop, $deliveryStops, $distanceBefore, $distanceBefore);
        }

        $this->optimizationLogger->logStep(
            OptimizationStepCategory::OPTIMIZER_CALL,
            sprintf('Optimizando orden de %d paradas', \count($deliveryStops)),
        );

        // Build optimizer-neutral vehicle with constraints from Route's vehicle
        $optimizableVehicle = $this->buildVehicleFromRoute(
            $route,
            startLat: $originStop?->getLatitude(),
            startLng: $originStop?->getLongitude(),
            endLat: $originStop?->getLatitude(),
            endLng: $originStop?->getLongitude(),
        );

        $optimizableJobs = [];
        $stopMap = [];

        foreach ($deliveryStops as $index => $stop) {
            if ($stop->getLatitude() === null || $stop->getLongitude() === null) {
                continue;
            }

            $optimizableJobs[] = $this->buildJobFromStop($stop, $index);
            $stopMap[$index] = $stop;
        }

        if (\count($optimizableJobs) < 2) {
            return $this->buildResult($originStop, $deliveryStops, $distanceBefore, $distanceBefore);
        }

        $result = $this->routeOptimizer->optimize([$optimizableVehicle], $optimizableJobs);

        // Extract optimized order from result
        $optimizedDeliveries = [];
        if (isset($result->routes[0])) {
            foreach ($result->routes[0]->steps as $step) {
                if ($step->type === 'job' && isset($stopMap[$step->jobId])) {
                    $optimizedDeliveries[] = $stopMap[$step->jobId];
                }
            }
        }

        // Distance and duration from optimizer
        $distanceAfter = isset($result->routes[0])
            ? $result->routes[0]->distanceMeters / 1000.0
            : $distanceBefore;
        $durationMinutes = isset($result->routes[0])
            ? (int) round($result->routes[0]->durationSeconds / 60.0)
            : 0;

        $improvement = $distanceBefore > 0
            ? round(($distanceBefore - $distanceAfter) / $distanceBefore * 100, 1)
            : 0;

        $this->optimizationLogger->logStep(
            OptimizationStepCategory::DISTANCE_CALCULATION,
            sprintf('Distancia optimizada: %.2fkm (ahorro: %.1f%%)', $distanceAfter, $improvement),
            ['distanceAfterKm' => round($distanceAfter, 2), 'improvementPercent' => $improvement, 'durationMinutes' => $durationMinutes],
        );

        // Log new stop order
        $orderNames = [];
        foreach ($optimizedDeliveries as $stop) {
            $orderNames[] = $stop->getRecipientName() ?? $stop->getAddress();
        }
        $this->optimizationLogger->logStep(
            OptimizationStepCategory::STOP_ORDERING,
            sprintf('Nuevo orden de %d paradas establecido', \count($optimizedDeliveries)),
            ['optimizedOrder' => $orderNames],
        );

        return $this->buildResult($originStop, $optimizedDeliveries, $distanceBefore, $distanceAfter, $durationMinutes);
    }

    /**
     * Applies the optimized order to the route, persisting new sequences.
     *
     * @param list<array{stop: RouteStop, newSequence: int}> $optimized
     */
    public function applyOptimizedOrder(array $optimized): void
    {
        foreach ($optimized as $item) {
            $item['stop']->setSequence($item['newSequence']);
        }

        $this->stopRepo->flush();
    }

    /**
     * Re-optimizes only PENDING stops on an active route.
     *
     * Uses the driver's current position (or the route origin) as the start point,
     * filters out already-delivered/exception/skipped stops, and reorders pending
     * stops via VROOM + OSRM.
     *
     * @return array{
     *     optimized: list<array{stop: RouteStop, newSequence: int}>,
     *     distanceBefore: float,
     *     distanceAfter: float,
     *     durationMinutes: int,
     * }
     */
    public function reoptimizePendingStops(Route $route, ?float $currentLat = null, ?float $currentLng = null): array
    {
        $allStops = $this->getStopsForRoute($route);

        // Separate origin, pending, and non-pending stops
        $originStop = null;
        $pendingStops = [];
        $maxNonPendingSeq = -1;

        foreach ($allStops as $stop) {
            if ($stop->isOrigin()) {
                $originStop = $stop;
                continue;
            }
            if ($stop->getStatus() === RouteStopStatus::PENDING) {
                $pendingStops[] = $stop;
            } else {
                $maxNonPendingSeq = max($maxNonPendingSeq, $stop->getSequence());
            }
        }

        $nonPendingCount = \count($allStops) - \count($pendingStops) - ($originStop !== null ? 1 : 0);
        $this->optimizationLogger->logStep(
            OptimizationStepCategory::STOP_ORDERING,
            sprintf('Paradas completadas/excluidas: %d, pendientes: %d', $nonPendingCount, \count($pendingStops)),
            ['nonPendingCount' => $nonPendingCount, 'pendingCount' => \count($pendingStops)],
        );

        if (\count($pendingStops) < 2) {
            $distanceBefore = $this->calculatePendingDistance($pendingStops, $currentLat, $currentLng, $originStop);

            return $this->buildPendingResult($pendingStops, $maxNonPendingSeq + 1, $distanceBefore, $distanceBefore);
        }

        // Determine start position: use current driver position, or fall back to origin
        $startLat = $currentLat ?? $originStop?->getLatitude();
        $startLng = $currentLng ?? $originStop?->getLongitude();

        $this->optimizationLogger->logStep(
            OptimizationStepCategory::OPTIMIZER_CALL,
            sprintf('Re-optimizando %d paradas pendientes desde [%.4f, %.4f]',
                \count($pendingStops), $startLat ?? 0, $startLng ?? 0),
            ['pendingCount' => \count($pendingStops), 'startLat' => $startLat, 'startLng' => $startLng,
             'usingDriverPosition' => $currentLat !== null],
        );

        $distanceBefore = $this->calculatePendingDistance($pendingStops, $startLat, $startLng, null);

        // Build optimizer-neutral vehicle with constraints from Route's vehicle
        $optimizableVehicle = $this->buildVehicleFromRoute(
            $route,
            startLat: $startLat,
            startLng: $startLng,
            endLat: null,
            endLng: null,
        );

        $optimizableJobs = [];
        $stopMap = [];

        foreach ($pendingStops as $index => $stop) {
            if ($stop->getLatitude() === null || $stop->getLongitude() === null) {
                continue;
            }

            $optimizableJobs[] = $this->buildJobFromStop($stop, $index);
            $stopMap[$index] = $stop;
        }

        if (\count($optimizableJobs) < 2) {
            return $this->buildPendingResult($pendingStops, $maxNonPendingSeq + 1, $distanceBefore, $distanceBefore);
        }

        $result = $this->routeOptimizer->optimize([$optimizableVehicle], $optimizableJobs);

        $optimizedPending = [];
        if (isset($result->routes[0])) {
            foreach ($result->routes[0]->steps as $step) {
                if ($step->type === 'job' && isset($stopMap[$step->jobId])) {
                    $optimizedPending[] = $stopMap[$step->jobId];
                }
            }
        }

        $distanceAfter = isset($result->routes[0])
            ? $result->routes[0]->distanceMeters / 1000.0
            : $distanceBefore;
        $durationMinutes = isset($result->routes[0])
            ? (int) round($result->routes[0]->durationSeconds / 60.0)
            : 0;

        return $this->buildPendingResult($optimizedPending, $maxNonPendingSeq + 1, $distanceBefore, $distanceAfter, $durationMinutes);
    }

    /**
     * Gets real road distance between two stops via OSRM.
     */
    public function distanceBetweenStops(RouteStop $a, RouteStop $b): ?float
    {
        if ($a->getLatitude() === null || $a->getLongitude() === null
            || $b->getLatitude() === null || $b->getLongitude() === null) {
            return null;
        }

        $result = $this->routingEngine->route(
            $a->getLatitude(),
            $a->getLongitude(),
            $b->getLatitude(),
            $b->getLongitude(),
        );

        return $result->distanceKm;
    }

    /**
     * Gets real road distance and duration between two coordinates via OSRM.
     *
     * @return array{distanceKm: float, durationSeconds: float}
     */
    public function getRoadDistance(float $fromLat, float $fromLng, float $toLat, float $toLng): array
    {
        $result = $this->routingEngine->route($fromLat, $fromLng, $toLat, $toLng);

        return ['distanceKm' => $result->distanceKm, 'durationSeconds' => $result->durationSeconds];
    }

    /**
     * Estimates route timing using OSRM real road distances and durations.
     *
     * @return array{
     *     totalDistanceKm: float,
     *     drivingTimeMinutes: float,
     *     deliveryTimeMinutes: float,
     *     totalTimeMinutes: float,
     * }
     */
    public function estimateRouteTiming(Route $route, float $avgSpeedKmh = 40.0, float $deliveryMinutesPerStop = 5.0): array
    {
        $stops = $this->getStopsForRoute($route);
        $deliveryCount = 0;

        foreach ($stops as $stop) {
            if (!$stop->isOrigin()) {
                $deliveryCount++;
            }
        }

        // Build waypoints for routing engine
        $waypoints = [];
        foreach ($stops as $stop) {
            if ($stop->getLatitude() !== null && $stop->getLongitude() !== null) {
                $waypoints[] = new Coordinate($stop->getLatitude(), $stop->getLongitude());
            }
        }

        if (\count($waypoints) < 2) {
            $deliveryTime = $deliveryCount * $deliveryMinutesPerStop;
            return [
                'totalDistanceKm' => 0.0,
                'drivingTimeMinutes' => 0.0,
                'deliveryTimeMinutes' => $deliveryTime,
                'totalTimeMinutes' => $deliveryTime,
            ];
        }

        $routeResult = $this->routingEngine->routeWithWaypoints($waypoints);

        $drivingTime = $routeResult->totalDurationSeconds / 60.0;
        $deliveryTime = $deliveryCount * $deliveryMinutesPerStop;

        return [
            'totalDistanceKm' => round($routeResult->totalDistanceKm, 2),
            'drivingTimeMinutes' => round($drivingTime, 1),
            'deliveryTimeMinutes' => $deliveryTime,
            'totalTimeMinutes' => round($drivingTime + $deliveryTime, 1),
        ];
    }

    /**
     * Calculates total road distance for a list of stops via OSRM.
     *
     * @param list<RouteStop> $stops
     */
    private function calculateTotalRoadDistance(array $stops): float
    {
        $waypoints = [];
        foreach ($stops as $stop) {
            if ($stop->getLatitude() !== null && $stop->getLongitude() !== null) {
                $waypoints[] = new Coordinate($stop->getLatitude(), $stop->getLongitude());
            }
        }

        if (\count($waypoints) < 2) {
            return 0.0;
        }

        $result = $this->routingEngine->routeWithWaypoints($waypoints);

        return $result->totalDistanceKm;
    }

    /**
     * @return array{
     *     optimized: list<array{stop: RouteStop, newSequence: int}>,
     *     distanceBefore: float,
     *     distanceAfter: float,
     *     durationMinutes: int,
     * }
     */
    private function buildResult(?RouteStop $originStop, array $deliveryStops, float $distanceBefore, float $distanceAfter, int $durationMinutes = 0): array
    {
        $result = [];
        $seq = 0;

        if ($originStop !== null) {
            $result[] = ['stop' => $originStop, 'newSequence' => $seq];
            $seq++;
        }

        foreach ($deliveryStops as $stop) {
            $result[] = ['stop' => $stop, 'newSequence' => $seq];
            $seq++;
        }

        return [
            'optimized' => $result,
            'distanceBefore' => $distanceBefore,
            'distanceAfter' => $distanceAfter,
            'durationMinutes' => $durationMinutes,
        ];
    }

    /**
     * Builds an OptimizableJob from a RouteStop, extracting constraints from its Shipment.
     */
    private function buildJobFromStop(RouteStop $stop, int $id): OptimizableJob
    {
        $shipment = $stop->getShipment();

        if ($shipment === null) {
            return new OptimizableJob(
                id: $id,
                latitude: $stop->getLatitude(),
                longitude: $stop->getLongitude(),
                serviceTimeSeconds: 300,
            );
        }

        $timeWindows = [];
        $windowStart = $shipment->getPreferredWindowStart();
        $windowEnd = $shipment->getPreferredWindowEnd();
        if ($windowStart !== null && $windowEnd !== null) {
            $timeWindows[] = [
                'start' => $this->timeToSeconds($windowStart),
                'end' => $this->timeToSeconds($windowEnd),
            ];
        }

        return new OptimizableJob(
            id: $id,
            latitude: $stop->getLatitude(),
            longitude: $stop->getLongitude(),
            serviceTimeSeconds: $shipment->getServiceTimeSeconds() ?? 300,
            weightKg: $shipment->getTotalWeightKg() ?? 0.0,
            volumeM3: $shipment->getTotalVolumeM3() ?? 0.0,
            parcels: $shipment->getTotalParcels(),
            priority: $shipment->getPriority()->toVroomPriority(),
            timeWindows: $timeWindows,
            requiredSkills: array_map(static fn(VehicleSkill $s) => (string) $s->value, $shipment->getRequiredSkills()),
        );
    }

    /**
     * Builds an OptimizableVehicle from a Route, extracting capacity and skills from its Vehicle.
     */
    private function buildVehicleFromRoute(Route $route, ?float $startLat = null, ?float $startLng = null, ?float $endLat = null, ?float $endLng = null): OptimizableVehicle
    {
        $vehicle = $route->getVehicle();

        if ($vehicle === null) {
            return new OptimizableVehicle(
                id: 0,
                startLatitude: $startLat,
                startLongitude: $startLng,
                endLatitude: $endLat,
                endLongitude: $endLng,
            );
        }

        return new OptimizableVehicle(
            id: 0,
            startLatitude: $startLat,
            startLongitude: $startLng,
            endLatitude: $endLat,
            endLongitude: $endLng,
            maxWeightKg: $vehicle->getMaxWeightKg(),
            maxVolumeM3: $vehicle->getMaxVolumeM3(),
            maxParcels: $vehicle->getMaxParcels(),
            skills: array_map(static fn(VehicleSkill $s) => (string) $s->value, $vehicle->getSkills()),
        );
    }

    private function timeToSeconds(\DateTimeImmutable $time): int
    {
        return (int) $time->format('H') * 3600
            + (int) $time->format('i') * 60
            + (int) $time->format('s');
    }

    /**
     * @return list<RouteStop>
     */
    private function getStopsForRoute(Route $route): array
    {
        return $this->stopRepo->findByRoute($route);
    }

    /**
     * Calculates total road distance for pending stops from the starting position.
     *
     * @param list<RouteStop> $pendingStops
     */
    private function calculatePendingDistance(array $pendingStops, ?float $startLat, ?float $startLng, ?RouteStop $originStop): float
    {
        $waypoints = [];

        $lat = $startLat ?? $originStop?->getLatitude();
        $lng = $startLng ?? $originStop?->getLongitude();

        if ($lat !== null && $lng !== null) {
            $waypoints[] = new Coordinate($lat, $lng);
        }

        foreach ($pendingStops as $stop) {
            if ($stop->getLatitude() !== null && $stop->getLongitude() !== null) {
                $waypoints[] = new Coordinate($stop->getLatitude(), $stop->getLongitude());
            }
        }

        if (\count($waypoints) < 2) {
            return 0.0;
        }

        $result = $this->routingEngine->routeWithWaypoints($waypoints);

        return $result->totalDistanceKm;
    }

    /**
     * @param list<RouteStop> $pendingStops
     *
     * @return array{
     *     optimized: list<array{stop: RouteStop, newSequence: int}>,
     *     distanceBefore: float,
     *     distanceAfter: float,
     *     durationMinutes: int,
     * }
     */
    private function buildPendingResult(array $pendingStops, int $startSequence, float $distanceBefore, float $distanceAfter, int $durationMinutes = 0): array
    {
        $result = [];
        $seq = $startSequence;

        foreach ($pendingStops as $stop) {
            $result[] = ['stop' => $stop, 'newSequence' => $seq];
            $seq++;
        }

        return [
            'optimized' => $result,
            'distanceBefore' => $distanceBefore,
            'distanceAfter' => $distanceAfter,
            'durationMinutes' => $durationMinutes,
        ];
    }
}
