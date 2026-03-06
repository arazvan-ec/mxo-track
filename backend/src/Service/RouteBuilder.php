<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Customer;
use App\Entity\CustomerLocation;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\Shipment;
use App\Entity\Vehicle;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Builds optimized delivery routes using the VROOM VRP solver.
 *
 * VROOM handles both the assignment of shipments to vehicles (respecting
 * capacity constraints) and the optimal ordering of stops (using real
 * road distances via OSRM).
 */
final class RouteBuilder
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VroomApiClient $vroomClient,
        private readonly VroomRequestMapper $requestMapper,
        private readonly VroomResponseMapper $responseMapper,
    ) {}

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

        // Convert domain entities to VROOM format
        $vehicleData = $this->requestMapper->mapVehicles($vehicles, $origin, $maxStopsPerRoute);
        $jobData = $this->requestMapper->mapJobs($shipments);

        if (\count($jobData['vroomJobs']) === 0) {
            return [];
        }

        // Call VROOM optimizer
        $vroomResponse = $this->vroomClient->optimize(
            $vehicleData['vroomVehicles'],
            $jobData['vroomJobs'],
        );

        // Convert VROOM response back to domain entities
        $result = $this->responseMapper->mapToRoutes(
            $vroomResponse,
            $vehicleData['vehicleMap'],
            $jobData['shipmentMap'],
            $customer,
            $origin,
        );

        return $result['routes'];
    }
}
