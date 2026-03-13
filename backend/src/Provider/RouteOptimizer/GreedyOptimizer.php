<?php

declare(strict_types=1);

namespace App\Provider\RouteOptimizer;

use App\Enum\OptimizationStepCategory;
use App\RouteOptimization\OptimizableJob;
use App\RouteOptimization\OptimizableVehicle;
use App\RouteOptimization\OptimizationResult;
use App\RouteOptimization\OptimizedRoute;
use App\RouteOptimization\OptimizedStep;
use App\RouteOptimization\RouteOptimizerInterface;
use App\Service\OptimizationLogger;

/**
 * Greedy nearest-neighbor route optimizer. Pure PHP, zero external dependencies.
 * Assigns jobs to nearest available vehicle respecting capacity, then orders
 * stops using nearest-neighbor from vehicle start position.
 */
final class GreedyOptimizer implements RouteOptimizerInterface
{
    private const EARTH_RADIUS_KM = 6371.0;

    public function __construct(
        private readonly OptimizationLogger $optimizationLogger,
    ) {
    }

    public function optimize(array $vehicles, array $jobs): OptimizationResult
    {
        $this->optimizationLogger->setOptimizerUsed('greedy');
        $this->optimizationLogger->logStep(
            OptimizationStepCategory::OPTIMIZER_SELECTION,
            'Usando optimizador Greedy (nearest-neighbor, sin dependencias externas)',
        );
        if ($jobs === []) {
            return new OptimizationResult(routes: [], unassignedJobIds: []);
        }

        if ($vehicles === []) {
            return new OptimizationResult(
                routes: [],
                unassignedJobIds: array_map(fn(OptimizableJob $j) => $j->id, $jobs),
            );
        }

        // Track remaining capacity per vehicle
        $vehicleCapacity = [];
        foreach ($vehicles as $vehicle) {
            $vehicleCapacity[$vehicle->id] = [
                'weight' => $vehicle->maxWeightKg ?? PHP_FLOAT_MAX,
                'volume' => $vehicle->maxVolumeM3 ?? PHP_FLOAT_MAX,
                'parcels' => $vehicle->maxParcels ?? PHP_INT_MAX,
                'tasks' => $vehicle->maxTasks ?? PHP_INT_MAX,
            ];
        }

        // Assigned jobs per vehicle (by vehicle id)
        /** @var array<int|string, list<OptimizableJob>> $assignments */
        $assignments = [];
        foreach ($vehicles as $vehicle) {
            $assignments[$vehicle->id] = [];
        }

        $unassigned = [];
        $remainingJobs = $jobs;

        // Sort by priority descending (higher priority first)
        usort($remainingJobs, fn(OptimizableJob $a, OptimizableJob $b) => $b->priority <=> $a->priority);

        // Assign each job to the nearest vehicle that has capacity
        foreach ($remainingJobs as $job) {
            $bestVehicle = null;
            $bestDistance = PHP_FLOAT_MAX;

            foreach ($vehicles as $vehicle) {
                $cap = $vehicleCapacity[$vehicle->id];

                // Check capacity
                if ($job->weightKg > $cap['weight']
                    || $job->volumeM3 > $cap['volume']
                    || $job->parcels > $cap['parcels']
                    || count($assignments[$vehicle->id]) >= $cap['tasks']
                ) {
                    continue;
                }

                // Distance from vehicle start (or 0,0 if no start)
                $vLat = $vehicle->startLatitude ?? 0.0;
                $vLng = $vehicle->startLongitude ?? 0.0;
                $distance = $this->haversine($vLat, $vLng, $job->latitude, $job->longitude);

                if ($distance < $bestDistance) {
                    $bestDistance = $distance;
                    $bestVehicle = $vehicle;
                }
            }

            if ($bestVehicle !== null) {
                $assignments[$bestVehicle->id][] = $job;
                $vehicleCapacity[$bestVehicle->id]['weight'] -= $job->weightKg;
                $vehicleCapacity[$bestVehicle->id]['volume'] -= $job->volumeM3;
                $vehicleCapacity[$bestVehicle->id]['parcels'] -= $job->parcels;

                $this->optimizationLogger->logStep(
                    OptimizationStepCategory::STOP_ORDERING,
                    sprintf('Job %d → Vehiculo %d (distancia=%.2fkm, capacidad restante: peso=%.1fkg)',
                        $job->id, $bestVehicle->id, $bestDistance, $vehicleCapacity[$bestVehicle->id]['weight']),
                    ['jobId' => $job->id, 'vehicleId' => $bestVehicle->id, 'distance' => round($bestDistance, 2),
                     'remainingCapacity' => $vehicleCapacity[$bestVehicle->id]],
                );
            } else {
                $unassigned[] = $job->id;

                $this->optimizationLogger->logStep(
                    OptimizationStepCategory::UNASSIGNED_JOBS,
                    sprintf('Job %d sin vehiculo disponible con capacidad suficiente', $job->id),
                    ['jobId' => $job->id, 'weightKg' => $job->weightKg, 'volumeM3' => $job->volumeM3, 'parcels' => $job->parcels],
                );
            }
        }

        // Build routes with nearest-neighbor ordering
        $routes = [];
        foreach ($vehicles as $vehicle) {
            $vehicleJobs = $assignments[$vehicle->id];
            if ($vehicleJobs === []) {
                continue;
            }

            $ordered = $this->nearestNeighborOrder(
                $vehicle->startLatitude ?? 0.0,
                $vehicle->startLongitude ?? 0.0,
                $vehicleJobs,
            );

            $steps = [];
            foreach ($ordered as $job) {
                $steps[] = new OptimizedStep(
                    jobId: $job->id,
                    type: 'job',
                    arrivalSeconds: 0,
                    serviceSeconds: $job->serviceTimeSeconds,
                );
            }

            $routes[] = new OptimizedRoute(
                vehicleId: $vehicle->id,
                steps: $steps,
            );
        }

        $this->optimizationLogger->logStep(
            OptimizationStepCategory::RESULT_SUMMARY,
            sprintf('Greedy: %d rutas creadas, %d sin asignar', \count($routes), \count($unassigned)),
            ['routeCount' => \count($routes), 'unassignedCount' => \count($unassigned)],
        );

        return new OptimizationResult(routes: $routes, unassignedJobIds: $unassigned);
    }

    /**
     * @param list<OptimizableJob> $jobs
     * @return list<OptimizableJob>
     */
    private function nearestNeighborOrder(float $startLat, float $startLng, array $jobs): array
    {
        $remaining = $jobs;
        $ordered = [];
        $currentLat = $startLat;
        $currentLng = $startLng;

        while ($remaining !== []) {
            $bestIdx = 0;
            $bestDist = PHP_FLOAT_MAX;

            foreach ($remaining as $idx => $job) {
                $dist = $this->haversine($currentLat, $currentLng, $job->latitude, $job->longitude);
                if ($dist < $bestDist) {
                    $bestDist = $dist;
                    $bestIdx = $idx;
                }
            }

            $ordered[] = $remaining[$bestIdx];
            $currentLat = $remaining[$bestIdx]->latitude;
            $currentLng = $remaining[$bestIdx]->longitude;
            array_splice($remaining, $bestIdx, 1);
        }

        return $ordered;
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
