<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Customer;
use App\Entity\CustomerLocation;
use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteStop;
use App\Domain\Shipment\Model\Shipment;
use App\Entity\Vehicle;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Converts VROOM API response into domain entities (Route, RouteStop).
 *
 * @deprecated Entity creation from optimization results is now handled
 *             directly in RouteBuilder using OptimizationResult value objects.
 */
final class VroomResponseMapper
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RouteCapacityValidator $capacityValidator,
    ) {}

    /**
     * @param array<int, Vehicle>  $vehicleMap   vroomId => Vehicle
     * @param array<int, Shipment> $shipmentMap  vroomId => Shipment
     * @return array{
     *     routes: list<array{route: Route, stops: list<RouteStop>, validation: array}>,
     *     unassigned: list<Shipment>,
     * }
     */
    public function mapToRoutes(
        array $vroomResponse,
        array $vehicleMap,
        array $shipmentMap,
        Customer $customer,
        ?CustomerLocation $origin,
    ): array {
        $routes = [];
        $routeNumber = 1;

        foreach ($vroomResponse['routes'] ?? [] as $vroomRoute) {
            $vehicleId = $vroomRoute['vehicle'];
            $vehicle = $vehicleMap[$vehicleId] ?? null;

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

            // Add delivery stops from VROOM's optimized order
            foreach ($vroomRoute['steps'] ?? [] as $step) {
                if ($step['type'] !== 'job') {
                    continue;
                }

                $jobId = $step['id'];
                $shipment = $shipmentMap[$jobId] ?? null;

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

            // Set distance and duration from VROOM's real calculations
            $distanceKm = ($vroomRoute['distance'] ?? 0) / 1000.0;
            $durationMinutes = (int) round(($vroomRoute['duration'] ?? 0) / 60.0);

            $route->setTotalDistanceKm($distanceKm);
            $route->setEstimatedDurationMinutes($durationMinutes);

            // Validate capacity (pass in-memory stops since Route is not yet flushed)
            $validation = $this->capacityValidator->validate($route, $stops);

            $routes[] = [
                'route' => $route,
                'stops' => $stops,
                'validation' => $validation,
            ];

            $routeNumber++;
        }

        // Collect unassigned shipments
        $unassigned = [];
        foreach ($vroomResponse['unassigned'] ?? [] as $item) {
            $jobId = $item['id'];
            if (isset($shipmentMap[$jobId])) {
                $unassigned[] = $shipmentMap[$jobId];
            }
        }

        return [
            'routes' => $routes,
            'unassigned' => $unassigned,
        ];
    }
}
