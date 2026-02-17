<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Vehicle;
use App\Service\TraccarApiClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:traccar:sync-devices', description: 'Sincroniza nombres de dispositivos Traccar con vehículos.')]
class TraccarSyncDevicesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TraccarApiClient $traccarApiClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Aplicar cambios en BD');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $apply = (bool) $input->getOption('apply');
        $devices = $this->traccarApiClient->getDevices();
        $vehicles = $this->entityManager->getRepository(Vehicle::class)->findAll();

        $changes = 0;
        foreach ($vehicles as $vehicle) {
            if ($vehicle->getTraccarDeviceId() !== null) {
                continue;
            }

            foreach ($devices as $device) {
                $name = mb_strtolower((string) ($device['name'] ?? ''));
                if ($name === mb_strtolower($vehicle->getName())) {
                    $vehicle->setTraccarDeviceId((int) $device['id']);
                    $changes++;
                    $output->writeln(sprintf('match: %s -> device #%d', $vehicle->getName(), (int) $device['id']));
                    break;
                }
            }
        }

        if ($apply) {
            $this->entityManager->flush();
            $output->writeln(sprintf('Cambios aplicados: %d', $changes));
        } else {
            $output->writeln(sprintf('Dry-run, matches detectados: %d', $changes));
        }

        return self::SUCCESS;
    }
}
