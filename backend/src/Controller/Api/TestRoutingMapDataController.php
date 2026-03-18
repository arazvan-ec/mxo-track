<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Customer;
use App\Entity\CustomerLocation;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\Shipment;
use App\Entity\Vehicle;
use App\Enum\OptimizationOperation;
use App\Enum\OptimizationStepCategory;
use App\Provider\ProviderUnavailableException;
use App\Routing\Coordinate;
use App\Routing\OsrmRoutingEngine;
use App\Service\OptimizationLogger;
use App\Service\RouteBuilder;
use App\Service\RouteOptimizationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route as SymfonyRoute;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class TestRoutingMapDataController extends AbstractController
{
    private const DELIVERIES = [
        ['Calle Gran Vía 1, 28013 Madrid', 40.4200, -3.7025, 'María García'],
        ['Calle de Alcalá 50, 28014 Madrid', 40.4190, -3.6950, 'Carlos López'],
        ['Calle de Serrano 45, 28001 Madrid', 40.4260, -3.6880, 'Ana Martínez'],
        ['Paseo de la Castellana 100, 28046 Madrid', 40.4500, -3.6920, 'Javier Ruiz'],
        ['Calle de Embajadores 70, 28012 Madrid', 40.4060, -3.7020, 'Francisco Romero'],
        ['Calle de la Princesa 25, 28008 Madrid', 40.4310, -3.7150, 'Óscar Ibáñez'],
        ['Calle de Bravo Murillo 100, 28020 Madrid', 40.4450, -3.7040, 'Adriana Parra'],
        ['Calle de Narváez 40, 28009 Madrid', 40.4220, -3.6780, 'Verónica Soto'],
        ['Avenida de Menéndez Pelayo 60, 28007 Madrid', 40.4150, -3.6800, 'David Prieto'],
        ['Calle de Ponzano 40, 28003 Madrid', 40.4380, -3.6960, 'Álvaro Pascual'],
    ];

    private const WAREHOUSE_LAT = 40.3460;
    private const WAREHOUSE_LNG = -3.6970;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RouteOptimizationService $optimizationService,
        private readonly OsrmRoutingEngine $routingEngine,
        private readonly OptimizationLogger $optimizationLogger,
        private readonly RouteBuilder $routeBuilder,
    ) {}

    #[SymfonyRoute('/api/map/test-routing', name: 'api_map_test_routing', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        try {
            $this->optimizationLogger->startOperation(OptimizationOperation::TEST_ROUTING, [
                'deliveryCount' => \count(self::DELIVERIES),
                'vehicleCount' => 2,
            ]);

            // Create test data
            [$vehicles, $shipments, $customer, $warehouse] = $this->createTestData();
            $this->em->flush();

            // Original route polyline (all stops, single route, no optimization)
            $waypointsBefore = [new Coordinate(self::WAREHOUSE_LAT, self::WAREHOUSE_LNG)];
            $allStopsBefore = [];
            foreach ($shipments as $i => $shipment) {
                $waypointsBefore[] = new Coordinate($shipment->getLatitude(), $shipment->getLongitude());
                $allStopsBefore[] = [
                    'seq' => $i + 1,
                    'recipient' => $shipment->getRecipientName(),
                    'address' => $shipment->getAddress(),
                    'lat' => $shipment->getLatitude(),
                    'lng' => $shipment->getLongitude(),
                ];
            }
            $waypointsBefore[] = new Coordinate(self::WAREHOUSE_LAT, self::WAREHOUSE_LNG);
            $routeBefore = $this->routingEngine->routeWithWaypoints($waypointsBefore);

            // Build optimized routes
            $builtRoutes = $this->routeBuilder->buildRoutes($shipments, $vehicles, $customer, $warehouse, 5);
            $this->em->flush();

            // Process each route
            $routesData = [];
            $totalDistanceAfterKm = 0.0;
            $totalDurationMinutes = 0;
            $osrmAvailable = true;

            foreach ($builtRoutes as $builtRoute) {
                $route = $builtRoute['route'];
                $stops = $builtRoute['stops'];

                $stopsBeforeOpt = [];
                foreach ($stops as $stop) {
                    if ($stop->isOrigin()) {
                        continue;
                    }
                    $stopsBeforeOpt[] = [
                        'seq' => $stop->getSequence(),
                        'recipient' => $stop->getRecipientName(),
                        'address' => $stop->getAddress(),
                        'lat' => $stop->getLatitude(),
                        'lng' => $stop->getLongitude(),
                    ];
                }

                $optResult = $this->optimizationService->optimizeStopOrder($route);
                $this->optimizationService->applyOptimizedOrder($optResult['optimized']);

                $stopsAfterOpt = [];
                $waypointsAfter = [new Coordinate(self::WAREHOUSE_LAT, self::WAREHOUSE_LNG)];
                foreach ($optResult['optimized'] as $item) {
                    $stop = $item['stop'];
                    if ($stop->isOrigin()) {
                        continue;
                    }
                    $waypointsAfter[] = new Coordinate($stop->getLatitude(), $stop->getLongitude());
                    $stopsAfterOpt[] = [
                        'seq' => $item['newSequence'],
                        'recipient' => $stop->getRecipientName(),
                        'address' => $stop->getAddress(),
                        'lat' => $stop->getLatitude(),
                        'lng' => $stop->getLongitude(),
                    ];
                }
                $waypointsAfter[] = new Coordinate(self::WAREHOUSE_LAT, self::WAREHOUSE_LNG);

                $osrmResult = $this->routingEngine->routeWithWaypoints($waypointsAfter);
                if ($osrmResult->geometry === null) {
                    $osrmAvailable = false;
                }

                $distanceAfterKm = $osrmResult->totalDistanceKm > 0
                    ? round($osrmResult->totalDistanceKm, 2)
                    : round($optResult['distanceAfter'], 2);

                $timing = $this->optimizationService->estimateRouteTiming($route);
                $totalDistanceAfterKm += $distanceAfterKm;
                $totalDurationMinutes += $timing['totalTimeMinutes'] ?? 0;

                $routesData[] = [
                    'name' => $route->getName(),
                    'vehicle' => $route->getVehicle()?->getName(),
                    'stopsBefore' => $stopsBeforeOpt,
                    'stopsAfter' => $stopsAfterOpt,
                    'polylineAfter' => $osrmResult->geometry,
                    'distanceBeforeKm' => round($optResult['distanceBefore'], 2),
                    'distanceAfterKm' => $distanceAfterKm,
                    'savedPercent' => $optResult['distanceBefore'] > 0
                        ? round(($optResult['distanceBefore'] - $distanceAfterKm) / $optResult['distanceBefore'] * 100, 1)
                        : 0,
                    'durationMinutes' => $optResult['durationMinutes'],
                    'timing' => $timing,
                    'stopCount' => \count($stopsAfterOpt),
                ];
            }

            $distanceBeforeKm = $routeBefore->totalDistanceKm > 0
                ? round($routeBefore->totalDistanceKm, 2)
                : round(array_sum(array_column($routesData, 'distanceBeforeKm')), 2);

            $totalDistanceAfterKm = round($totalDistanceAfterKm, 2);
            $globalSavedPercent = $distanceBeforeKm > 0
                ? round(($distanceBeforeKm - $totalDistanceAfterKm) / $distanceBeforeKm * 100, 1)
                : 0;

            $result = [
                'origin' => [
                    'lat' => self::WAREHOUSE_LAT,
                    'lng' => self::WAREHOUSE_LNG,
                    'address' => 'Polígono Industrial de Villaverde, Madrid',
                ],
                'allStopsBefore' => $allStopsBefore,
                'polylineBefore' => $routeBefore->geometry,
                'osrmAvailable' => $osrmAvailable,
                'routesData' => $routesData,
                'metrics' => [
                    'distanceBeforeKm' => $distanceBeforeKm,
                    'distanceAfterKm' => $totalDistanceAfterKm,
                    'savedPercent' => $globalSavedPercent,
                    'totalDurationMinutes' => $totalDurationMinutes,
                    'stopCount' => \count(self::DELIVERIES),
                    'routeCount' => \count($routesData),
                ],
            ];
        } catch (\Throwable $e) {
            $this->cleanup();

            return $this->json([
                'error' => $e->getMessage(),
                'hint' => $e instanceof ProviderUnavailableException
                    ? 'Check that OSRM and VROOM are running and reachable.'
                    : null,
            ], 500);
        }

        $this->cleanup();

        return $this->json($result);
    }

    /**
     * @return array{list<Vehicle>, list<Shipment>, Customer, CustomerLocation}
     */
    private function createTestData(): array
    {
        $customer = new Customer('Test Routing Customer');
        $customer->setAddress('Test Address, Madrid');
        $customer->setContactPhone('600000000');

        $warehouse = new CustomerLocation($customer, 'Almacén Test', 'Polígono Industrial de Villaverde, Madrid');
        $warehouse->setLatitude(self::WAREHOUSE_LAT);
        $warehouse->setLongitude(self::WAREHOUSE_LNG);
        $warehouse->setDefault(true);

        $vehicle1 = new Vehicle('Test Vehicle A');
        $vehicle1->setMaxWeightKg(500.0);
        $vehicle1->setMaxVolumeM3(4.0);
        $vehicle1->setMaxParcels(25);

        $vehicle2 = new Vehicle('Test Vehicle B');
        $vehicle2->setMaxWeightKg(500.0);
        $vehicle2->setMaxVolumeM3(4.0);
        $vehicle2->setMaxParcels(25);

        $this->em->persist($customer);
        $this->em->persist($warehouse);
        $this->em->persist($vehicle1);
        $this->em->persist($vehicle2);

        $shipments = [];
        foreach (self::DELIVERIES as $i => [$address, $lat, $lng, $name]) {
            $shipment = new Shipment(sprintf('TEST-RT-%04d', $i + 1), $customer);
            $shipment->setRecipientName($name);
            $shipment->setAddress($address);
            $shipment->setLatitude($lat);
            $shipment->setLongitude($lng);
            $shipment->setTotalWeightKg(round(mt_rand(100, 1500) / 100, 2));
            $shipment->setTotalVolumeM3(round(mt_rand(1, 50) / 100, 2));
            $shipment->setTotalParcels(1);
            $this->em->persist($shipment);
            $shipments[] = $shipment;
        }

        return [[$vehicle1, $vehicle2], $shipments, $customer, $warehouse];
    }

    private function cleanup(): void
    {
        $conn = $this->em->getConnection();
        $conn->executeStatement("DELETE FROM route_stop WHERE route_id IN (SELECT r.id FROM route_plan r JOIN customer c ON r.customer_id = c.id WHERE c.name = 'Test Routing Customer')");
        $conn->executeStatement("DELETE FROM route_plan WHERE customer_id IN (SELECT id FROM customer WHERE name = 'Test Routing Customer')");
        $conn->executeStatement("DELETE FROM shipment WHERE customer_id IN (SELECT id FROM customer WHERE name = 'Test Routing Customer')");
        $conn->executeStatement("DELETE FROM vehicle WHERE name IN ('Test Vehicle', 'Test Vehicle A', 'Test Vehicle B')");
        $conn->executeStatement("DELETE FROM customer_location WHERE customer_id IN (SELECT id FROM customer WHERE name = 'Test Routing Customer')");
        $conn->executeStatement("DELETE FROM customer WHERE name = 'Test Routing Customer'");
    }
}
