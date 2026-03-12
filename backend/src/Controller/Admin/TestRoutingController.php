<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Customer;
use App\Entity\CustomerLocation;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\Shipment;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Enum\UserRole;
use App\Service\RouteOptimizationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
    ) {
    }

    #[SymfonyRoute('/run', name: 'admin_test_routing_run', methods: ['GET'])]
    public function run(): JsonResponse
    {
        $log = [];
        $success = true;

        try {
            // --- Step 1: Create test data ---
            $log[] = ['step' => '1. Creating test data', 'status' => 'running'];

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

            $stopsBefore = [];
            foreach ($shipments as $i => $shipment) {
                $stop = new RouteStop($route, $i + 1, $shipment->getAddress());
                $stop->setLatitude($shipment->getLatitude());
                $stop->setLongitude($shipment->getLongitude());
                $stop->setRecipientName($shipment->getRecipientName());
                $stop->setShipment($shipment);
                $this->em->persist($stop);
                $stopsBefore[] = ['seq' => $i + 1, 'recipient' => $shipment->getRecipientName(), 'address' => $shipment->getAddress()];
            }

            $this->em->flush();
            $log[] = ['step' => '1. Creating test data', 'status' => 'ok', 'detail' => sprintf('%d shipments, 1 route', \count($shipments))];

            // --- Step 2: Test OSRM point-to-point ---
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

            // --- Step 3: Optimize with VROOM ---
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

            // --- Step 4: Route timing ---
            $log[] = ['step' => '4. Estimating timing (OSRM multi-waypoint)', 'status' => 'running'];
            $timing = $this->optimizationService->estimateRouteTiming($route);
            $log[] = [
                'step' => '4. Estimating timing (OSRM multi-waypoint)',
                'status' => 'ok',
                'timing' => $timing,
            ];
        } catch (\Throwable $e) {
            $success = false;
            $log[] = ['step' => 'ERROR', 'status' => 'failed', 'detail' => $e->getMessage()];
        } finally {
            // Always cleanup test data
            $this->cleanup();
        }

        return new JsonResponse([
            'success' => $success,
            'stopsBefore' => $stopsBefore ?? [],
            'stopsAfter' => $stopsAfter ?? [],
            'log' => $log,
        ]);
    }

    private function cleanup(): void
    {
        $conn = $this->em->getConnection();
        $conn->executeStatement("DELETE FROM route_stop WHERE route_id IN (SELECT r.id FROM route_plan r JOIN customer c ON r.customer_id = c.id WHERE c.name = 'Test Routing Customer')");
        $conn->executeStatement("DELETE FROM route_plan WHERE customer_id IN (SELECT id FROM customer WHERE name = 'Test Routing Customer')");
        $conn->executeStatement("DELETE FROM shipment WHERE customer_id IN (SELECT id FROM customer WHERE name = 'Test Routing Customer')");
        $conn->executeStatement("DELETE FROM vehicle WHERE name = 'Test Vehicle'");
        $conn->executeStatement("DELETE FROM customer_location WHERE customer_id IN (SELECT id FROM customer WHERE name = 'Test Routing Customer')");
        $conn->executeStatement("DELETE FROM customer WHERE name = 'Test Routing Customer'");
    }
}
