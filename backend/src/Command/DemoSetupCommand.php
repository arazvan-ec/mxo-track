<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\DemoScenarioBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:demo:setup',
    description: 'Create a complete demo scenario with realistic Madrid delivery data',
)]
final class DemoSetupCommand extends Command
{
    public function __construct(
        private readonly DemoScenarioBuilder $scenarioBuilder,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('shipments', null, InputOption::VALUE_REQUIRED, 'Number of shipments to create', '40')
            ->addOption('fresh', null, InputOption::VALUE_NONE, 'Purge existing demo data before creating')
            ->addOption('skip-routes', null, InputOption::VALUE_NONE, 'Skip route building (useful when VROOM is unavailable)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $shipmentCount = max(1, (int) $input->getOption('shipments'));
        $skipRoutes = (bool) $input->getOption('skip-routes');

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

        if (!$skipRoutes) {
            $io->section('Building optimized routes...');
            $io->warning('Route building requires VROOM. Use --skip-routes to skip.');
            // TODO: integrate RoutePlanningService when VROOM is available
        }

        return Command::SUCCESS;
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
