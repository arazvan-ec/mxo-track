<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Customer;
use App\Entity\CustomerLocation;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\Shipment;
use App\Entity\Vehicle;
use App\Enum\RouteStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Builds routes from a list of shipments, distributing them across vehicles
 * respecting each vehicle's capacity constraints (weight, volume, parcels).
 *
 * Algorithm:
 * 1. Sort shipments by distance from origin (farthest first)
 * 2. For each vehicle, fill with shipments that fit
 * 3. Optimize each route's stop order using nearest-neighbor
 */
final class RouteBuilder
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RouteOptimizationService $optimizer,
        private readonly RouteCapacityValidator $capacityValidator,
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

        // Get origin coordinates for distance sorting
        $originLat = $origin?->getLatitude();
        $originLng = $origin?->getLongitude();

        // Sort shipments by distance from origin (farthest first)
        if ($originLat !== null && $originLng !== null) {
            usort($shipments, function (Shipment $a, Shipment $b) use ($originLat, $originLng): int {
                $distA = $this->distanceFromOrigin($a, $originLat, $originLng);
                $distB = $this->distanceFromOrigin($b, $originLat, $originLng);
                return $distB <=> $distA; // Farthest first
            });
        }

        $routes = [];
        $unassigned = $shipments;
        $vehicleIndex = 0;

        while (\count($unassigned) > 0 && $vehicleIndex < \count($vehicles)) {
            $vehicle = $vehicles[$vehicleIndex];
            $routeShipments = [];
            $currentWeight = 0.0;
            $currentVolume = 0.0;
            $currentParcels = 0;
            $remaining = [];

            foreach ($unassigned as $shipment) {
                if (\count($routeShipments) >= $maxStopsPerRoute) {
                    $remaining[] = $shipment;
                    continue;
                }

                if ($this->capacityValidator->canFitShipment($vehicle, $shipment, $currentWeight, $currentVolume, $currentParcels)) {
                    $routeShipments[] = $shipment;
                    $currentWeight += $shipment->getTotalWeightKg() ?? 0.0;
                    $currentVolume += $shipment->getTotalVolumeM3() ?? 0.0;
                    $currentParcels += $shipment->getTotalParcels();
                } else {
                    $remaining[] = $shipment;
                }
            }

            if (\count($routeShipments) > 0) {
                $routeResult = $this->createRoute(
                    $routeShipments,
                    $vehicle,
                    $customer,
                    $origin,
                    $vehicleIndex + 1,
                );
                $routes[] = $routeResult;
            }

            $unassigned = $remaining;
            $vehicleIndex++;
        }

        // If we still have unassigned shipments, create overflow routes on last vehicle
        if (\count($unassigned) > 0 && \count($vehicles) > 0) {
            $lastVehicle = $vehicles[\count($vehicles) - 1];
            $chunks = array_chunk($unassigned, $maxStopsPerRoute);
            foreach ($chunks as $i => $chunk) {
                $routeResult = $this->createRoute(
                    $chunk,
                    $lastVehicle,
                    $customer,
                    $origin,
                    \count($routes) + 1,
                );
                $routes[] = $routeResult;
            }
        }

        return $routes;
    }

    /**
     * @param list<Shipment> $shipments
     * @return array{route: Route, stops: list<RouteStop>, validation: array}
     */
    private function createRoute(
        array $shipments,
        Vehicle $vehicle,
        Customer $customer,
        ?CustomerLocation $origin,
        int $routeNumber,
    ): array {
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

        // Add delivery stops
        foreach ($shipments as $shipment) {
            $stop = new RouteStop($route, $seq, $shipment->getAddress() ?? 'Sin dirección');
            $stop->setShipment($shipment);
            $stop->setLatitude($shipment->getLatitude());
            $stop->setLongitude($shipment->getLongitude());
            $stop->setRecipientName($shipment->getRecipientName());
            $stop->setRecipientPhone($shipment->getRecipientPhone());

            // Copy delivery window preferences from shipment
            $stop->setDeliveryWindowStart($shipment->getPreferredWindowStart());
            $stop->setDeliveryWindowEnd($shipment->getPreferredWindowEnd());

            $this->em->persist($stop);
            $stops[] = $stop;
            $seq++;
        }

        // Optimize stop order
        $optimization = $this->optimizer->optimizeStopOrder($route);
        $this->optimizer->applyOptimizedOrder($optimization['optimized']);
        $route->setTotalDistanceKm($optimization['distanceAfter']);

        // Validate capacity
        $validation = $this->capacityValidator->validate($route);

        // Estimate duration (avg 40 km/h driving + 5 min per delivery)
        $drivingMinutes = ($optimization['distanceAfter'] / 40.0) * 60;
        $deliveryMinutes = \count($shipments) * 5;
        $route->setEstimatedDurationMinutes((int) round($drivingMinutes + $deliveryMinutes));

        return [
            'route' => $route,
            'stops' => $stops,
            'validation' => $validation,
        ];
    }

    private function distanceFromOrigin(Shipment $shipment, float $originLat, float $originLng): float
    {
        $lat = $shipment->getLatitude();
        $lng = $shipment->getLongitude();

        if ($lat === null || $lng === null) {
            return 0.0;
        }

        return $this->optimizer->calculateDistance($originLat, $originLng, $lat, $lng);
    }
}
