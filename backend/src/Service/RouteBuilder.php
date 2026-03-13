<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Customer;
use App\Entity\CustomerLocation;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\Shipment;
use App\Entity\Vehicle;
use App\Enum\OptimizationStepCategory;
use App\Enum\VehicleSkill;
use App\RouteOptimization\OptimizableJob;
use App\RouteOptimization\OptimizableVehicle;
use App\RouteOptimization\OptimizationResult;
use App\RouteOptimization\RouteOptimizerInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Builds optimized delivery routes using a VRP solver.
 *
 * Converts domain entities to optimizer-neutral value objects, calls the
 * RouteOptimizerInterface port, then materializes the result as Route
 * and RouteStop entities.
 */
final class RouteBuilder
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RouteOptimizerInterface $optimizer,
        private readonly RouteCapacityValidator $capacityValidator,
        private readonly OptimizationLogger $optimizationLogger,
        private readonly RouteSnapshotManager $snapshotManager,
    ) {
    }

    /**
     * @param list<Shipment> $shipments
     * @param list<Vehicle> $vehicles
     * @return list<array{route: Route, stops: list<RouteStop>, validation: array}>
     */
    public function buildRoutes(
        array $shipments,
        array $vehicles,
        Customer $customer,
        ?CustomerLocation $origin = null,
        int $maxStopsPerRoute = 30,
    ): array {
        if (\count($shipments) === 0 || \count($vehicles) === 0) {
            return [];
        }

        // Convert domain entities to optimizer-neutral value objects
        $optimizableVehicles = $this->mapVehiclesToOptimizable($vehicles, $origin, $maxStopsPerRoute);

        $this->optimizationLogger->logStep(
            OptimizationStepCategory::VEHICLE_MAPPING,
            sprintf('Mapeados %d vehiculos a formato optimizable', \count($optimizableVehicles)),
            ['vehicleCount' => \count($optimizableVehicles), 'originSet' => $origin !== null],
        );

        $totalShipments = \count($shipments);
        $optimizableJobs = $this->mapShipmentsToOptimizable($shipments);
        $skipped = $totalShipments - \count($optimizableJobs);

        $this->optimizationLogger->logStep(
            OptimizationStepCategory::JOB_MAPPING,
            sprintf('Mapeados %d shipments a jobs (%d descartados sin coordenadas)', \count($optimizableJobs), $skipped),
            ['jobCount' => \count($optimizableJobs), 'skippedNoCoords' => $skipped],
        );

        if ($optimizableJobs === []) {
            return [];
        }

        // Call optimizer
        $this->optimizationLogger->logStep(
            OptimizationStepCategory::OPTIMIZER_CALL,
            sprintf('Llamando al optimizador con %d vehiculos y %d jobs', \count($optimizableVehicles), \count($optimizableJobs)),
        );

        $result = $this->optimizer->optimize($optimizableVehicles, $optimizableJobs);

        $this->optimizationLogger->logStep(
            OptimizationStepCategory::RESULT_SUMMARY,
            sprintf('Optimizador genero %d rutas, %d sin asignar', \count($result->routes), \count($result->unassignedJobIds)),
            ['routeCount' => \count($result->routes), 'unassignedCount' => \count($result->unassignedJobIds)],
        );

        // Materialize as domain entities
        $materializedRoutes = $this->materializeRoutes($result, $vehicles, $shipments, $customer, $origin);

        // Flush to assign IDs before creating snapshots (snapshots query by route)
        $this->em->flush();

        foreach ($materializedRoutes as $mr) {
            $route = $mr['route'];
            $this->optimizationLogger->logStep(
                OptimizationStepCategory::CAPACITY_CHECK,
                sprintf('Ruta "%s": %d paradas, dist=%.1fkm', $route->getName(), \count($mr['stops']), $route->getTotalDistanceKm() ?? 0),
                ['routeName' => $route->getName(), 'stopsCount' => \count($mr['stops']), 'validation' => $mr['validation']],
            );

            // Create RouteSnapshot with optimization results
            $originalStopOrder = array_map(static fn(RouteStop $s) => [
                'sequence' => $s->getSequence(),
                'address' => $s->getAddress(),
                'recipientName' => $s->getRecipientName(),
                'lat' => $s->getLatitude(),
                'lng' => $s->getLongitude(),
                'isOrigin' => $s->isOrigin(),
            ], $mr['stops']);

            $this->snapshotManager->createSnapshot(
                $route,
                originalStopOrder: $originalStopOrder,
            );
        }

        return $materializedRoutes;
    }

    /**
     * @param list<Vehicle> $vehicles
     * @return list<OptimizableVehicle>
     */
    private function mapVehiclesToOptimizable(array $vehicles, ?CustomerLocation $origin, int $maxTasks): array
    {
        $result = [];

        foreach ($vehicles as $index => $vehicle) {
            $startLat = $origin?->getLatitude();
            $startLng = $origin?->getLongitude();

            $result[] = new OptimizableVehicle(
                id: $index,
                startLatitude: $startLat,
                startLongitude: $startLng,
                endLatitude: $startLat,
                endLongitude: $startLng,
                maxWeightKg: $vehicle->getMaxWeightKg(),
                maxVolumeM3: $vehicle->getMaxVolumeM3(),
                maxParcels: $vehicle->getMaxParcels(),
                maxTasks: $maxTasks,
                skills: array_map(static fn(VehicleSkill $s) => (string) $s->value, $vehicle->getSkills()),
            );
        }

        return $result;
    }

    /**
     * @param list<Shipment> $shipments
     * @return list<OptimizableJob>
     */
    private function mapShipmentsToOptimizable(array $shipments): array
    {
        $result = [];

        foreach ($shipments as $index => $shipment) {
            if ($shipment->getLatitude() === null || $shipment->getLongitude() === null) {
                continue;
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

            $result[] = new OptimizableJob(
                id: $index,
                latitude: $shipment->getLatitude(),
                longitude: $shipment->getLongitude(),
                serviceTimeSeconds: $shipment->getServiceTimeSeconds() ?? 300,
                weightKg: $shipment->getTotalWeightKg() ?? 0.0,
                volumeM3: $shipment->getTotalVolumeM3() ?? 0.0,
                parcels: $shipment->getTotalParcels(),
                priority: $shipment->getPriority()->toVroomPriority(),
                timeWindows: $timeWindows,
                requiredSkills: array_map(static fn(VehicleSkill $s) => (string) $s->value, $shipment->getRequiredSkills()),
            );
        }

        return $result;
    }

    /**
     * @param list<Vehicle>  $vehicles
     * @param list<Shipment> $shipments
     * @return list<array{route: Route, stops: list<RouteStop>, validation: array}>
     */
    private function materializeRoutes(
        OptimizationResult $result,
        array $vehicles,
        array $shipments,
        Customer $customer,
        ?CustomerLocation $origin,
    ): array {
        $routes = [];
        $routeNumber = 1;

        foreach ($result->routes as $optimizedRoute) {
            $vehicle = $vehicles[$optimizedRoute->vehicleId] ?? null;

            if ($vehicle === null) {
                continue;
            }

            $route = new Route(sprintf('Ruta %d - %s', $routeNumber, date('d/m/Y')));
            $route->setVehicle($vehicle);
            $route->setCustomer($customer);
            $route->setOriginLocation($origin);
            $this->em->persist($route);

            $stops = [];
            $seq = 0;

            // Add origin stop
            if ($origin !== null) {
                $originStop = new RouteStop($route, $seq, $origin->getAddress());
                $originStop->setOrigin(true);
                $originStop->setLatitude($origin->getLatitude());
                $originStop->setLongitude($origin->getLongitude());
                $this->em->persist($originStop);
                $stops[] = $originStop;
                $seq++;
            }

            // Add delivery stops in optimized order
            foreach ($optimizedRoute->steps as $step) {
                if ($step->type !== 'job') {
                    continue;
                }

                $shipment = $shipments[$step->jobId] ?? null;

                if ($shipment === null) {
                    continue;
                }

                $stop = new RouteStop($route, $seq, $shipment->getAddress() ?? 'Sin dirección');
                $stop->setShipment($shipment);
                $stop->setLatitude($shipment->getLatitude());
                $stop->setLongitude($shipment->getLongitude());
                $stop->setRecipientName($shipment->getRecipientName());
                $stop->setRecipientPhone($shipment->getRecipientPhone());
                $stop->setDeliveryWindowStart($shipment->getPreferredWindowStart());
                $stop->setDeliveryWindowEnd($shipment->getPreferredWindowEnd());
                $this->em->persist($stop);
                $stops[] = $stop;
                $seq++;
            }

            // Set distance and duration
            $route->setTotalDistanceKm($optimizedRoute->distanceMeters / 1000.0);
            $route->setEstimatedDurationMinutes((int) round($optimizedRoute->durationSeconds / 60.0));

            // Validate capacity (pass in-memory stops since Route is not yet flushed)
            $validation = $this->capacityValidator->validate($route, $stops);

            $routes[] = [
                'route' => $route,
                'stops' => $stops,
                'validation' => $validation,
            ];

            $routeNumber++;
        }

        return $routes;
    }

    private function timeToSeconds(\DateTimeImmutable $time): int
    {
        return (int) $time->format('H') * 3600
            + (int) $time->format('i') * 60
            + (int) $time->format('s');
    }
}
