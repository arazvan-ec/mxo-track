<?php

declare(strict_types=1);

namespace App\Command;

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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:demo:test-routing',
    description: 'Create test data and run VROOM + OSRM optimization to verify routing services',
)]
final class TestRoutingCommand extends Command
{
    private const DELIVERIES = [
        ['Calle Gran Vía 1, 28013 Madrid', 40.4200, -3.7025, 'María García', '612345001'],
        ['Calle de Alcalá 50, 28014 Madrid', 40.4190, -3.6950, 'Carlos López', '612345002'],
        ['Calle de Serrano 45, 28001 Madrid', 40.4260, -3.6880, 'Ana Martínez', '612345003'],
        ['Paseo de la Castellana 100, 28046 Madrid', 40.4500, -3.6920, 'Javier Ruiz', '612345006'],
        ['Calle de Embajadores 70, 28012 Madrid', 40.4060, -3.7020, 'Francisco Romero', '612345012'],
        ['Calle de la Princesa 25, 28008 Madrid', 40.4310, -3.7150, 'Óscar Ibáñez', '612345026'],
        ['Calle de Bravo Murillo 100, 28020 Madrid', 40.4450, -3.7040, 'Adriana Parra', '612345029'],
        ['Calle de Narváez 40, 28009 Madrid', 40.4220, -3.6780, 'Verónica Soto', '612345037'],
        ['Avenida de Menéndez Pelayo 60, 28007 Madrid', 40.4150, -3.6800, 'David Prieto', '612345040'],
        ['Calle de Ponzano 40, 28003 Madrid', 40.4380, -3.6960, 'Álvaro Pascual', '612345032'],
    ];

    private const WAREHOUSE_LAT = 40.3460;
    private const WAREHOUSE_LNG = -3.6970;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RouteOptimizationService $optimizationService,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('cleanup', null, InputOption::VALUE_NONE, 'Remove test data after running (no permanent changes to DB)')
            ->addOption('stops', null, InputOption::VALUE_REQUIRED, 'Number of delivery stops (2-10)', '10');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $cleanup = (bool) $input->getOption('cleanup');
        $stopCount = min(10, max(2, (int) $input->getOption('stops')));

        $io->title('Test Routing Services (VROOM + OSRM)');

        // --- Step 1: Create test data ---
        $io->section('1. Creating test data');

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

        $driver = new User('test-routing-driver@test.local');
        $driver->setName('Test Driver');
        $driver->assignRole(UserRole::DRIVER);
        $driver->setPassword($this->passwordHasher->hashPassword($driver, 'test1234'));
        $driver->setActive(true);
        $driver->setCustomer($customer);

        $this->em->persist($customer);
        $this->em->persist($warehouse);
        $this->em->persist($vehicle);
        $this->em->persist($driver);

        // Create shipments
        $shipments = [];
        for ($i = 0; $i < $stopCount; $i++) {
            [$address, $lat, $lng, $name, $phone] = self::DELIVERIES[$i];
            $shipment = new Shipment(sprintf('TEST-RT-%04d', $i + 1), $customer);
            $shipment->setRecipientName($name);
            $shipment->setRecipientPhone($phone);
            $shipment->setAddress($address);
            $shipment->setLatitude($lat);
            $shipment->setLongitude($lng);
            $shipment->setTotalWeightKg(round(mt_rand(100, 1500) / 100, 2));
            $shipment->setTotalVolumeM3(round(mt_rand(1, 50) / 100, 2));
            $shipment->setTotalParcels(1);
            $this->em->persist($shipment);
            $shipments[] = $shipment;
        }

        // Create route with stops
        $route = new Route('Test Routing Route');
        $route->setCustomer($customer);
        $route->setVehicle($vehicle);
        $route->setDriver($driver);
        $route->setOriginLocation($warehouse);
        $this->em->persist($route);

        // Origin stop (warehouse)
        $originStop = new RouteStop($route, 0, 'Polígono Industrial de Villaverde, Madrid');
        $originStop->setLatitude(self::WAREHOUSE_LAT);
        $originStop->setLongitude(self::WAREHOUSE_LNG);
        $originStop->setOrigin(true);
        $this->em->persist($originStop);

        // Delivery stops (in input order — not yet optimized)
        foreach ($shipments as $i => $shipment) {
            $stop = new RouteStop($route, $i + 1, $shipment->getAddress());
            $stop->setLatitude($shipment->getLatitude());
            $stop->setLongitude($shipment->getLongitude());
            $stop->setRecipientName($shipment->getRecipientName());
            $stop->setRecipientPhone($shipment->getRecipientPhone());
            $stop->setShipment($shipment);
            $this->em->persist($stop);
        }

        $this->em->flush();

        $io->success(sprintf('Created: 1 customer, 1 warehouse, 1 vehicle, 1 driver, %d shipments, 1 route with %d stops', $stopCount, $stopCount + 1));

        // --- Step 2: Test point-to-point OSRM routing ---
        $io->section('2. Testing OSRM point-to-point routing');

        try {
            $p2p = $this->optimizationService->getRoadDistance(
                self::WAREHOUSE_LAT, self::WAREHOUSE_LNG,
                self::DELIVERIES[0][1], self::DELIVERIES[0][2],
            );
            $io->success(sprintf(
                'Warehouse → %s: %.2f km, %d min',
                self::DELIVERIES[0][0],
                $p2p['distanceKm'],
                (int) round($p2p['durationSeconds'] / 60),
            ));
        } catch (\Throwable $e) {
            $io->error(sprintf('OSRM routing failed: %s', $e->getMessage()));
            $io->warning('Is OSRM running? Check OSRM_URL env var.');
            $this->cleanupIfRequested($cleanup);

            return Command::FAILURE;
        }

        // --- Step 3: Optimize route with VROOM ---
        $io->section('3. Optimizing route with VROOM');

        $io->text('Stop order BEFORE optimization:');
        $this->printStopOrder($io, $route);

        try {
            $result = $this->optimizationService->optimizeStopOrder($route);
        } catch (\Throwable $e) {
            $io->error(sprintf('VROOM optimization failed: %s', $e->getMessage()));
            $io->warning('Is VROOM running? Check VROOM_URL env var.');
            $this->cleanupIfRequested($cleanup);

            return Command::FAILURE;
        }

        // Apply optimized order
        $this->optimizationService->applyOptimizedOrder($result['optimized']);

        $io->text('Stop order AFTER optimization:');
        $this->printStopOrder($io, $route);

        $saved = $result['distanceBefore'] > 0
            ? round(($result['distanceBefore'] - $result['distanceAfter']) / $result['distanceBefore'] * 100, 1)
            : 0;

        $io->table(
            ['Metric', 'Value'],
            [
                ['Distance before', sprintf('%.2f km', $result['distanceBefore'])],
                ['Distance after', sprintf('%.2f km', $result['distanceAfter'])],
                ['Distance saved', sprintf('%.1f%%', $saved)],
                ['Estimated duration', sprintf('%d min', $result['durationMinutes'])],
            ],
        );

        // --- Step 4: Estimate route timing ---
        $io->section('4. Estimating route timing (OSRM multi-waypoint)');

        try {
            $timing = $this->optimizationService->estimateRouteTiming($route);
            $io->table(
                ['Metric', 'Value'],
                [
                    ['Total distance', sprintf('%.2f km', $timing['totalDistanceKm'])],
                    ['Driving time', sprintf('%.1f min', $timing['drivingTimeMinutes'])],
                    ['Delivery time', sprintf('%.1f min (5 min/stop)', $timing['deliveryTimeMinutes'])],
                    ['Total time', sprintf('%.1f min', $timing['totalTimeMinutes'])],
                ],
            );
        } catch (\Throwable $e) {
            $io->error(sprintf('Route timing estimation failed: %s', $e->getMessage()));
        }

        // --- Cleanup ---
        $this->cleanupIfRequested($cleanup);
        if ($cleanup) {
            $io->note('Test data cleaned up (--cleanup flag).');
        } else {
            $io->note('Test data persisted in DB. Use --cleanup to auto-remove after test.');
        }

        $io->success('Routing services test completed successfully!');

        return Command::SUCCESS;
    }

    private function printStopOrder(SymfonyStyle $io, Route $route): void
    {
        $stops = $this->em->createQueryBuilder()
            ->select('s')
            ->from(RouteStop::class, 's')
            ->where('s.route = :route')
            ->setParameter('route', $route)
            ->orderBy('s.sequence', 'ASC')
            ->getQuery()
            ->getResult();

        $rows = [];
        foreach ($stops as $stop) {
            $rows[] = [
                $stop->getSequence(),
                $stop->isOrigin() ? '[ORIGIN]' : $stop->getRecipientName(),
                mb_substr($stop->getAddress(), 0, 45),
                sprintf('%.4f, %.4f', $stop->getLatitude(), $stop->getLongitude()),
            ];
        }

        $io->table(['#', 'Recipient', 'Address', 'Coordinates'], $rows);
    }

    private function cleanupIfRequested(bool $cleanup): void
    {
        if (!$cleanup) {
            return;
        }

        $conn = $this->em->getConnection();
        $conn->executeStatement("DELETE FROM route_stop WHERE route_id IN (SELECT r.id FROM route_plan r JOIN customer c ON r.customer_id = c.id WHERE c.name = 'Test Routing Customer')");
        $conn->executeStatement("DELETE FROM route_plan WHERE customer_id IN (SELECT id FROM customer WHERE name = 'Test Routing Customer')");
        $conn->executeStatement("DELETE FROM shipment WHERE customer_id IN (SELECT id FROM customer WHERE name = 'Test Routing Customer')");
        $conn->executeStatement("DELETE FROM \"user\" WHERE email = 'test-routing-driver@test.local'");
        $conn->executeStatement("DELETE FROM vehicle WHERE name = 'Test Vehicle'");
        $conn->executeStatement("DELETE FROM customer_location WHERE customer_id IN (SELECT id FROM customer WHERE name = 'Test Routing Customer')");
        $conn->executeStatement("DELETE FROM customer WHERE name = 'Test Routing Customer'");
    }
}
