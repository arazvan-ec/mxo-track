<?php

declare(strict_types=1);

namespace App\Service;

use App\RouteOptimization\OptimizableJob;
use App\RouteOptimization\OptimizableVehicle;
use App\RouteOptimization\VroomRouteOptimizer;

/**
 * @deprecated Use App\RouteOptimization\RouteOptimizerInterface instead.
 */
final class VroomApiClient
{
    public function __construct(
        private readonly VroomRouteOptimizer $optimizer,
    ) {
    }

    /**
     * @deprecated Use RouteOptimizerInterface::optimize() instead.
     *
     * @param list<array> $vehicles VROOM vehicle objects
     * @param list<array> $jobs    VROOM job objects
     * @return array{code: int, routes: list<array>, unassigned: list<array>, summary: array}
     */
    public function optimize(array $vehicles, array $jobs): array
    {
        $optimizableVehicles = [];
        foreach ($vehicles as $index => $v) {
            $start = $v['start'] ?? null;
            $end = $v['end'] ?? null;
            $capacity = $v['capacity'] ?? [999999, 999999, 9999];

            $optimizableVehicles[] = new OptimizableVehicle(
                id: $index,
                startLatitude: $start !== null ? $start[1] : null,
                startLongitude: $start !== null ? $start[0] : null,
                endLatitude: $end !== null ? $end[1] : null,
                endLongitude: $end !== null ? $end[0] : null,
                maxWeightKg: $capacity[0] / 1000.0,
                maxVolumeM3: $capacity[1] / 1_000_000.0,
                maxParcels: $capacity[2],
                maxTasks: $v['max_tasks'] ?? null,
                skills: array_map('strval', $v['skills'] ?? []),
            );
        }

        $optimizableJobs = [];
        foreach ($jobs as $index => $j) {
            $location = $j['location'] ?? [0, 0];
            $amount = $j['amount'] ?? [0, 0, 1];

            $optimizableJobs[] = new OptimizableJob(
                id: $index,
                latitude: $location[1],
                longitude: $location[0],
                serviceTimeSeconds: $j['service'] ?? 300,
                weightKg: $amount[0] / 1000.0,
                volumeM3: $amount[1] / 1_000_000.0,
                parcels: $amount[2] ?? 1,
                priority: $j['priority'] ?? 0,
                requiredSkills: array_map('strval', $j['skills'] ?? []),
            );
        }

        $result = $this->optimizer->optimize($optimizableVehicles, $optimizableJobs);

        // Convert back to legacy VROOM response format
        $routes = [];
        foreach ($result->routes as $route) {
            $steps = [];
            foreach ($route->steps as $step) {
                $steps[] = [
                    'type' => $step->type,
                    'id' => $step->jobId,
                    'arrival' => $step->arrivalSeconds,
                    'service' => $step->serviceSeconds,
                ];
            }

            $routes[] = [
                'vehicle' => $route->vehicleId,
                'steps' => $steps,
                'distance' => $route->distanceMeters,
                'duration' => $route->durationSeconds,
            ];
        }

        $unassigned = array_map(
            static fn($id) => ['id' => $id],
            $result->unassignedJobIds,
        );

        return [
            'code' => 0,
            'routes' => $routes,
            'unassigned' => $unassigned,
            'summary' => [],
        ];
    }
}
