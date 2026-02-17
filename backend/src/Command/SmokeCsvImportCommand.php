<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Customer;
use App\Service\ShipmentCsvImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:smoke:csv-import', description: 'Smoke test CLI para import CSV de envíos.')]
class SmokeCsvImportCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ShipmentCsvImporter $shipmentCsvImporter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('customer-id', null, InputOption::VALUE_REQUIRED, 'UUID del customer')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Ruta CSV de prueba');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $customerId = (string) $input->getOption('customer-id');
        $file = (string) $input->getOption('file');

        if ($customerId === '' || $file === '') {
            $output->writeln('smoke.csv_import.error=missing_options');
            return self::FAILURE;
        }

        $customer = $this->entityManager->find(Customer::class, $customerId);
        if (!$customer instanceof Customer) {
            $output->writeln('smoke.csv_import.error=customer_not_found');
            return self::FAILURE;
        }

        $result = $this->shipmentCsvImporter->import($file, $customer);

        $output->writeln(sprintf('smoke.csv_import.created=%d', $result['created']));
        $output->writeln(sprintf('smoke.csv_import.skipped=%d', $result['skipped']));

        return self::SUCCESS;
    }
}
