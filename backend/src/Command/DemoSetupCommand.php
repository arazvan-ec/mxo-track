<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\Route\BuildRoutesInput;
use App\Application\Route\RoutePlanningService;
use App\Service\DemoScenarioBuilder;
use App\Service\ShipmentCsvImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:demo:setup',
    description: 'Create a complete demo scenario with realistic Madrid delivery data',
)]
final class DemoSetupCommand extends Command
{
    public function __construct(
        private readonly DemoScenarioBuilder $scenarioBuilder,
        private readonly EntityManagerInterface $em,
        private readonly ShipmentCsvImporter $csvImporter,
        private readonly RoutePlanningService $routePlanningService,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir = '',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('shipments', null, InputOption::VALUE_REQUIRED, 'Number of shipments to create', '40')
            ->addOption('fresh', null, InputOption::VALUE_NONE, 'Purge existing demo data before creating')
            ->addOption('skip-routes', null, InputOption::VALUE_NONE, 'Skip route building (useful when VROOM is unavailable)')
            ->addOption('import-csv', null, InputOption::VALUE_NONE, 'Import shipments from docs/demo/envios-madrid.csv instead of generating them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $shipmentCount = max(1, (int) $input->getOption('shipments'));
        $skipRoutes = (bool) $input->getOption('skip-routes');
        $importCsv = (bool) $input->getOption('import-csv');

        $io->title('Demo Scenario Setup');

        if ($input->getOption('fresh')) {
            $io->section('Purging existing demo data...');
            $this->purgeExistingDemoData();
            $io->success('Demo data purged.');
        }

        $io->section('Building demo scenario...');
        $result = $this->scenarioBuilder->buildScenario($shipmentCount);

        $this->em->persist($result->customer);
        $this->em->persist($result->warehouse);
        $this->em->persist($result->customerUser);

        foreach ($result->vehicles as $vehicle) {
            $this->em->persist($vehicle);
        }
        foreach ($result->drivers as $driver) {
            $this->em->persist($driver);
        }
        foreach ($result->shipments as $shipment) {
            $this->em->persist($shipment);
        }

        $this->em->flush();

        $io->success(sprintf('Demo scenario created: "%s"', $result->customer->getName()));

        $io->table(
            ['Entity', 'Count'],
            [
                ['Customer', '1'],
                ['Warehouse', '1'],
                ['Vehicles', (string) \count($result->vehicles)],
                ['Drivers', (string) \count($result->drivers)],
                ['Shipments', (string) \count($result->shipments)],
            ],
        );

        if ($importCsv) {
            $io->section('Importing CSV demo shipments...');
            $csvPath = $this->resolveCsvPath();

            if (!is_file($csvPath)) {
                $io->error(sprintf('CSV file not found: %s', $csvPath));

                return Command::FAILURE;
            }

            $importResult = $this->csvImporter->import($csvPath, $result->customer);
            $io->success(sprintf(
                'CSV import: %d created, %d skipped, %d errors',
                $importResult['created'],
                $importResult['skipped'],
                $importResult['errors'],
            ));
        }

        if (!$skipRoutes) {
            $io->section('Building optimized routes...');

            $shipmentIds = array_map(
                static fn ($s) => $s->getPublicIdString(),
                $result->shipments,
            );
            $vehicleIds = array_map(
                static fn ($v) => $v->getPublicIdString(),
                $result->vehicles,
            );

            try {
                $buildResult = $this->routePlanningService->buildRoutes(new BuildRoutesInput(
                    shipmentPublicIds: $shipmentIds,
                    vehiclePublicIds: $vehicleIds,
                    originPublicId: $result->warehouse->getPublicIdString(),
                ));

                $io->success(sprintf('%d routes created.', $buildResult->routesCreated));
                foreach ($buildResult->routes as $routeData) {
                    $io->text(sprintf(
                        '  - %s (%s): %d stops, %.1f km',
                        $routeData['route']['name'],
                        $routeData['route']['vehicle'] ?? 'unassigned',
                        $routeData['stopsCount'],
                        $routeData['route']['totalDistanceKm'] ?? 0,
                    ));
                }
            } catch (\Throwable $e) {
                $io->error(sprintf('Route building failed: %s', $e->getMessage()));
                $io->warning('Use --skip-routes to skip route building if VROOM is unavailable.');
            }
        }

        return Command::SUCCESS;
    }

    private function resolveCsvPath(): string
    {
        if ($this->projectDir !== '') {
            return $this->projectDir . '/../docs/demo/envios-madrid.csv';
        }

        return \dirname(__DIR__, 3) . '/docs/demo/envios-madrid.csv';
    }

    private function purgeExistingDemoData(): void
    {
        $conn = $this->em->getConnection();
        $conn->executeStatement("DELETE FROM shipment WHERE customer_id IN (SELECT id FROM customer WHERE name = 'Logística Express Madrid')");
        $conn->executeStatement("DELETE FROM route_stop WHERE route_id IN (SELECT r.id FROM route r JOIN customer c ON r.customer_id = c.id WHERE c.name = 'Logística Express Madrid')");
        $conn->executeStatement("DELETE FROM route WHERE customer_id IN (SELECT id FROM customer WHERE name = 'Logística Express Madrid')");
        $conn->executeStatement("DELETE FROM vehicle WHERE name LIKE 'Furgoneta Madrid%' OR name LIKE 'Camión Refrigerado%' OR name LIKE 'Moto Express%'");
        $conn->executeStatement("DELETE FROM \"user\" WHERE email LIKE '%@demo.local'");
        $conn->executeStatement("DELETE FROM customer_location WHERE customer_id IN (SELECT id FROM customer WHERE name = 'Logística Express Madrid')");
        $conn->executeStatement("DELETE FROM customer WHERE name = 'Logística Express Madrid'");
    }
}
