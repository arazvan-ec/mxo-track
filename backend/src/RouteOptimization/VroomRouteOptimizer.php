<?php

declare(strict_types=1);

namespace App\RouteOptimization;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * VROOM adapter for the RouteOptimizer port.
 *
 * Encapsulates VROOM-specific details:
 * - [longitude, latitude] coordinate order
 * - Integer capacity units (grams, cm³, parcels)
 * - VROOM Express HTTP API (POST JSON)
 * - Response parsing (routes, unassigned, steps)
 */
final class VroomRouteOptimizer implements RouteOptimizerInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $vroomUrl,
    ) {
    }

    public function optimize(array $vehicles, array $jobs): OptimizationResult
    {
        if ($vehicles === [] || $jobs === []) {
            return new OptimizationResult(
                routes: [],
                unassignedJobIds: array_map(static fn(OptimizableJob $j) => $j->id, $jobs),
            );
        }

        $vroomVehicles = $this->mapVehicles($vehicles);
        $vroomJobs = $this->mapJobs($jobs);

        try {
            $data = $this->callVroom($vroomVehicles, $vroomJobs);
        } catch (\Throwable $e) {
            $this->logger->error('VROOM optimization request failed.', [
                'vehicleCount' => \count($vehicles),
                'jobCount' => \count($jobs),
                'error' => $e->getMessage(),
            ]);

            return new OptimizationResult(
                routes: [],
                unassignedJobIds: array_map(static fn(OptimizableJob $j) => $j->id, $jobs),
            );
        }

        return $this->mapResponse($data, $vehicles, $jobs);
    }

    /**
     * @param list<OptimizableVehicle> $vehicles
     * @return list<array>
     */
    private function mapVehicles(array $vehicles): array
    {
        $vroomVehicles = [];

        foreach ($vehicles as $index => $vehicle) {
            $v = [
                'id' => $index,
                'capacity' => [
                    $this->kgToGrams($vehicle->maxWeightKg),
                    $this->m3ToCm3($vehicle->maxVolumeM3),
                    $vehicle->maxParcels ?? 9999,
                ],
            ];

            if ($vehicle->maxTasks !== null) {
                $v['max_tasks'] = $vehicle->maxTasks;
            }

            if ($vehicle->startLatitude !== null && $vehicle->startLongitude !== null) {
                $coords = [$vehicle->startLongitude, $vehicle->startLatitude];
                $v['start'] = $coords;
            }

            if ($vehicle->endLatitude !== null && $vehicle->endLongitude !== null) {
                $v['end'] = [$vehicle->endLongitude, $vehicle->endLatitude];
            }

            if ($vehicle->skills !== []) {
                $v['skills'] = array_map('intval', $vehicle->skills);
            }

            $vroomVehicles[] = $v;
        }

        return $vroomVehicles;
    }

    /**
     * @param list<OptimizableJob> $jobs
     * @return list<array>
     */
    private function mapJobs(array $jobs): array
    {
        $vroomJobs = [];

        foreach ($jobs as $index => $job) {
            $j = [
                'id' => $index,
                'location' => [$job->longitude, $job->latitude],
                'service' => $job->serviceTimeSeconds,
                'amount' => [
                    $this->kgToGrams($job->weightKg),
                    $this->m3ToCm3($job->volumeM3),
                    $job->parcels,
                ],
                'priority' => $job->priority,
            ];

            if ($job->timeWindows !== []) {
                $j['time_windows'] = array_map(
                    static fn(array $tw) => [$tw['start'], $tw['end']],
                    $job->timeWindows,
                );
            }

            if ($job->requiredSkills !== []) {
                $j['skills'] = array_map('intval', $job->requiredSkills);
            }

            $vroomJobs[] = $j;
        }

        return $vroomJobs;
    }

    /**
     * @return array{code: int, routes: list<array>, unassigned: list<array>, summary: array}
     */
    private function callVroom(array $vroomVehicles, array $vroomJobs): array
    {
        $response = $this->httpClient->request('POST', $this->vroomUrl, [
            'json' => [
                'vehicles' => $vroomVehicles,
                'jobs' => $vroomJobs,
            ],
            'timeout' => 30,
        ]);

        $data = $response->toArray();

        if (($data['code'] ?? -1) !== 0) {
            throw new \RuntimeException(sprintf(
                'VROOM optimization failed (code %d): %s',
                $data['code'] ?? -1,
                $data['error'] ?? 'Unknown error',
            ));
        }

        return $data;
    }

    /**
     * @param list<OptimizableVehicle> $vehicles
     * @param list<OptimizableJob>     $jobs
     */
    private function mapResponse(array $data, array $vehicles, array $jobs): OptimizationResult
    {
        $routes = [];

        foreach ($data['routes'] ?? [] as $vroomRoute) {
            $vehicleIndex = $vroomRoute['vehicle'] ?? 0;
            $vehicleId = $vehicles[$vehicleIndex]->id ?? $vehicleIndex;

            $steps = [];
            foreach ($vroomRoute['steps'] ?? [] as $step) {
                $type = $step['type'] ?? 'unknown';
                $jobIndex = $step['id'] ?? 0;

                $jobId = ($type === 'job' && isset($jobs[$jobIndex]))
                    ? $jobs[$jobIndex]->id
                    : $jobIndex;

                $steps[] = new OptimizedStep(
                    jobId: $jobId,
                    type: $type,
                    arrivalSeconds: $step['arrival'] ?? 0,
                    serviceSeconds: $step['service'] ?? 0,
                );
            }

            $routes[] = new OptimizedRoute(
                vehicleId: $vehicleId,
                steps: $steps,
                distanceMeters: $vroomRoute['distance'] ?? 0,
                durationSeconds: $vroomRoute['duration'] ?? 0,
            );
        }

        $unassignedJobIds = [];
        foreach ($data['unassigned'] ?? [] as $unassigned) {
            $jobIndex = $unassigned['id'] ?? 0;
            $unassignedJobIds[] = $jobs[$jobIndex]->id ?? $jobIndex;
        }

        return new OptimizationResult(
            routes: $routes,
            unassignedJobIds: $unassignedJobIds,
        );
    }

    private function kgToGrams(?float $kg): int
    {
        return $kg !== null ? (int) round($kg * 1000) : 999999;
    }

    private function m3ToCm3(?float $m3): int
    {
        return $m3 !== null ? (int) round($m3 * 1_000_000) : 999999;
    }
}
