<?php

declare(strict_types=1);

namespace App\RouteOptimization;

use App\Enum\OptimizationStepCategory;
use App\Service\OptimizationLogger;
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
        private readonly OptimizationLogger $optimizationLogger,
        private readonly string $vroomUrl,
    ) {
    }

    public function optimize(array $vehicles, array $jobs): OptimizationResult
    {
        $this->optimizationLogger->setOptimizerUsed('vroom');

        if ($vehicles === [] || $jobs === []) {
            $this->optimizationLogger->logStep(
                OptimizationStepCategory::OPTIMIZER_CALL,
                'VROOM: sin vehiculos o jobs, retornando sin optimizar',
            );

            return new OptimizationResult(
                routes: [],
                unassignedJobIds: array_map(static fn(OptimizableJob $j) => $j->id, $jobs),
            );
        }

        // Log vehicle mapping
        foreach ($vehicles as $i => $v) {
            $this->optimizationLogger->logStep(
                OptimizationStepCategory::VEHICLE_MAPPING,
                sprintf('Vehiculo %d: peso max=%.1fkg, volumen max=%.2fm³, parcels max=%s, tasks max=%s',
                    $i, $v->maxWeightKg ?? 0, $v->maxVolumeM3 ?? 0, $v->maxParcels ?? 'ilim', $v->maxTasks ?? 'ilim'),
                ['vehicleId' => $v->id, 'skills' => $v->skills, 'startLat' => $v->startLatitude, 'startLng' => $v->startLongitude],
            );
        }

        // Log job mapping
        foreach ($jobs as $i => $j) {
            $this->optimizationLogger->logStep(
                OptimizationStepCategory::JOB_MAPPING,
                sprintf('Job %d: [%.4f,%.4f] peso=%.2fkg vol=%.3fm³ prioridad=%d',
                    $i, $j->latitude, $j->longitude, $j->weightKg, $j->volumeM3, $j->priority),
                ['jobId' => $j->id, 'timeWindows' => $j->timeWindows, 'requiredSkills' => $j->requiredSkills, 'serviceTimeSec' => $j->serviceTimeSeconds],
            );
        }

        $vroomVehicles = $this->mapVehicles($vehicles);
        $vroomJobs = $this->mapJobs($jobs);

        $this->optimizationLogger->logStep(
            OptimizationStepCategory::OPTIMIZER_CALL,
            sprintf('Enviando a VROOM: %d vehiculos, %d jobs', \count($vehicles), \count($jobs)),
            ['vroomUrl' => $this->vroomUrl],
        );

        try {
            $data = $this->callVroom($vroomVehicles, $vroomJobs);
        } catch (\Throwable $e) {
            $this->logger->error('VROOM optimization request failed.', [
                'vehicleCount' => \count($vehicles),
                'jobCount' => \count($jobs),
                'error' => $e->getMessage(),
            ]);

            $this->optimizationLogger->logStep(
                OptimizationStepCategory::OPTIMIZER_CALL,
                sprintf('VROOM fallo: %s', $e->getMessage()),
                ['error' => $e->getMessage()],
            );

            return new OptimizationResult(
                routes: [],
                unassignedJobIds: array_map(static fn(OptimizableJob $j) => $j->id, $jobs),
            );
        }

        $routeCount = \count($data['routes'] ?? []);
        $unassignedCount = \count($data['unassigned'] ?? []);
        $summaryDistance = $data['summary']['distance'] ?? 0;
        $summaryDuration = $data['summary']['duration'] ?? 0;

        $this->optimizationLogger->logStep(
            OptimizationStepCategory::OPTIMIZER_CALL,
            sprintf('VROOM respondio: %d rutas, %d sin asignar, distancia=%dm, duracion=%ds',
                $routeCount, $unassignedCount, $summaryDistance, $summaryDuration),
            ['routeCount' => $routeCount, 'unassignedCount' => $unassignedCount, 'summaryDistance' => $summaryDistance, 'summaryDuration' => $summaryDuration],
        );

        if ($unassignedCount > 0) {
            $unassignedIds = array_map(static fn(array $u) => $u['id'] ?? 0, $data['unassigned'] ?? []);
            $this->optimizationLogger->logStep(
                OptimizationStepCategory::UNASSIGNED_JOBS,
                sprintf('%d jobs no asignados por VROOM', $unassignedCount),
                ['unassignedJobIndices' => $unassignedIds],
            );
        }

        // Log step ordering per route
        foreach ($data['routes'] ?? [] as $ri => $vroomRoute) {
            $jobSteps = array_filter($vroomRoute['steps'] ?? [], static fn(array $s) => ($s['type'] ?? '') === 'job');
            $stepIds = array_map(static fn(array $s) => $s['id'] ?? 0, $jobSteps);
            $this->optimizationLogger->logStep(
                OptimizationStepCategory::STOP_ORDERING,
                sprintf('Ruta %d: %d paradas en orden optimizado', $ri, \count($stepIds)),
                ['vehicleIndex' => $vroomRoute['vehicle'] ?? 0, 'stepOrder' => array_values($stepIds),
                 'distanceM' => $vroomRoute['distance'] ?? 0, 'durationS' => $vroomRoute['duration'] ?? 0],
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

            if ($vehicle->shiftStartSeconds !== null && $vehicle->shiftEndSeconds !== null) {
                $v['time_window'] = [$vehicle->shiftStartSeconds, $vehicle->shiftEndSeconds];
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
