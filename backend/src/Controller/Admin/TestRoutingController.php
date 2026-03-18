<?php

declare(strict_types=1);

namespace App\Controller\Admin;

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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route as SymfonyRoute;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[SymfonyRoute('/admin/test-routing')]
#[IsGranted('ROLE_ADMIN')]
class TestRoutingController extends AbstractController
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
    ) {
    }

    #[SymfonyRoute('/osrm-check', name: 'admin_test_routing_osrm_check', methods: ['GET'])]
    public function osrmCheck(): JsonResponse
    {
        $start = microtime(true);
        $result = [
            'osrm' => ['ok' => false, 'latency_ms' => 0, 'has_geometry' => false, 'distance_km' => 0, 'error' => null],
            'vroom' => ['ok' => false, 'latency_ms' => 0, 'error' => null],
        ];

        // Test OSRM
        try {
            $osrmResult = $this->routingEngine->routeWithWaypoints([
                new Coordinate(40.4168, -3.7038), // Madrid Centro
                new Coordinate(40.4530, -3.6883), // Chamartin
            ]);
            $result['osrm']['ok'] = $osrmResult->totalDistanceKm > 0;
            $result['osrm']['has_geometry'] = $osrmResult->geometry !== null;
            $result['osrm']['distance_km'] = round($osrmResult->totalDistanceKm, 2);
        } catch (\Throwable $e) {
            $result['osrm']['error'] = $e->getMessage();
        }
        $result['osrm']['latency_ms'] = (int) round((microtime(true) - $start) * 1000);

        // Test VROOM via optimizeStopOrder with minimal test data
        $vroomStart = microtime(true);
        try {
            [$route] = $this->createTestData();
            $this->em->flush();
            $optimized = $this->optimizationService->optimizeStopOrder($route);
            $result['vroom']['ok'] = $optimized['distanceAfter'] > 0;
        } catch (\Throwable $e) {
            $result['vroom']['error'] = $e->getMessage();
        } finally {
            $this->cleanup();
        }
        $result['vroom']['latency_ms'] = (int) round((microtime(true) - $vroomStart) * 1000);

        return new JsonResponse($result);
    }

    #[SymfonyRoute('/run', name: 'admin_test_routing_run', methods: ['GET'])]
    public function run(): JsonResponse
    {
        $log = [];
        $success = true;

        try {
            $log[] = ['step' => '1. Creating test data', 'status' => 'running'];
            [$route, $shipments] = $this->createTestData();
            $this->em->flush();
            $log[] = ['step' => '1. Creating test data', 'status' => 'ok', 'detail' => sprintf('%d shipments, 1 route', \count($shipments))];

            $log[] = ['step' => '2. Testing OSRM (point-to-point)', 'status' => 'running'];
            $p2p = $this->optimizationService->getRoadDistance(
                self::WAREHOUSE_LAT, self::WAREHOUSE_LNG,
                self::DELIVERIES[0][1], self::DELIVERIES[0][2],
            );
            $log[] = [
                'step' => '2. Testing OSRM (point-to-point)',
                'status' => 'ok',
                'detail' => sprintf('Warehouse → %s: %.2f km, %d min', self::DELIVERIES[0][3], $p2p['distanceKm'], (int) round($p2p['durationSeconds'] / 60)),
            ];

            $log[] = ['step' => '3. Optimizing route (VROOM)', 'status' => 'running'];
            $result = $this->optimizationService->optimizeStopOrder($route);
            $this->optimizationService->applyOptimizedOrder($result['optimized']);

            $stopsAfter = [];
            foreach ($result['optimized'] as $item) {
                if (!$item['stop']->isOrigin()) {
                    $stopsAfter[] = [
                        'seq' => $item['newSequence'],
                        'recipient' => $item['stop']->getRecipientName(),
                        'address' => $item['stop']->getAddress(),
                    ];
                }
            }

            $saved = $result['distanceBefore'] > 0
                ? round(($result['distanceBefore'] - $result['distanceAfter']) / $result['distanceBefore'] * 100, 1)
                : 0;

            $log[] = [
                'step' => '3. Optimizing route (VROOM)',
                'status' => 'ok',
                'optimization' => [
                    'distanceBeforeKm' => round($result['distanceBefore'], 2),
                    'distanceAfterKm' => round($result['distanceAfter'], 2),
                    'savedPercent' => $saved,
                    'durationMinutes' => $result['durationMinutes'],
                ],
            ];

            $log[] = ['step' => '4. Estimating timing (OSRM multi-waypoint)', 'status' => 'running'];
            $timing = $this->optimizationService->estimateRouteTiming($route);
            $log[] = [
                'step' => '4. Estimating timing (OSRM multi-waypoint)',
                'status' => 'ok',
                'timing' => $timing,
            ];
        } catch (\Throwable $e) {
            $success = false;
            $hint = match (true) {
                $e instanceof ProviderUnavailableException => 'Check that OSRM and VROOM are running and reachable.',
                $e instanceof \InvalidArgumentException => 'Check your provider configuration and environment variables.',
                default => null,
            };
            $log[] = ['step' => 'ERROR', 'status' => 'failed', 'detail' => $e->getMessage(), 'hint' => $hint];
        } finally {
            $this->cleanup();
        }

        return new JsonResponse([
            'success' => $success,
            'stopsAfter' => $stopsAfter ?? [],
            'log' => $log,
        ]);
    }

    #[SymfonyRoute('/map', name: 'admin_test_routing_map', methods: ['GET'])]
    public function map(): Response
    {
        return $this->redirect('/app/admin/test-routing');
    }

    /**
     * @return array{Route, list<Shipment>}
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

        $vehicle = new Vehicle('Test Vehicle');
        $vehicle->setMaxWeightKg(1000.0);
        $vehicle->setMaxVolumeM3(8.0);
        $vehicle->setMaxParcels(50);

        $this->em->persist($customer);
        $this->em->persist($warehouse);
        $this->em->persist($vehicle);

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

        $route = new Route('Test Routing Route');
        $route->setCustomer($customer);
        $route->setVehicle($vehicle);
        $route->setOriginLocation($warehouse);
        $this->em->persist($route);

        $originStop = new RouteStop($route, 0, 'Polígono Industrial de Villaverde, Madrid');
        $originStop->setLatitude(self::WAREHOUSE_LAT);
        $originStop->setLongitude(self::WAREHOUSE_LNG);
        $originStop->setOrigin(true);
        $this->em->persist($originStop);

        foreach ($shipments as $i => $shipment) {
            $stop = new RouteStop($route, $i + 1, $shipment->getAddress());
            $stop->setLatitude($shipment->getLatitude());
            $stop->setLongitude($shipment->getLongitude());
            $stop->setRecipientName($shipment->getRecipientName());
            $stop->setShipment($shipment);
            $this->em->persist($stop);
        }

        return [$route, $shipments];
    }

    /**
     * Create test data for multi-route optimization: 2 vehicles, shipments (no routes).
     *
     * @return array{list<Vehicle>, list<Shipment>, Customer, CustomerLocation}
     */
    private function createTestDataMultiRoute(): array
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
