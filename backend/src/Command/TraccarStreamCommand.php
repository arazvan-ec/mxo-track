<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Vehicle;
use App\Entity\VehicleCheckpoint;
use App\Service\TraccarApiClient;
use App\Service\TraccarIngestionService;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:traccar:stream', description: 'Polling Traccar REST + backfill por checkpoint.')]
class TraccarStreamCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TraccarApiClient $traccarApiClient,
        private readonly TraccarIngestionService $ingestionService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('once', null, InputOption::VALUE_NONE, 'Ejecutar un ciclo y salir')
            ->addOption('sleep', null, InputOption::VALUE_REQUIRED, 'Segundos entre ciclos', '5');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $once = (bool) $input->getOption('once');
        $sleep = max(1, (int) $input->getOption('sleep'));

        do {
            $vehicles = $this->entityManager->getRepository(Vehicle::class)->findBy(['isActive' => true]);
            $ingested = 0;

            foreach ($vehicles as $vehicle) {
                $deviceId = $vehicle->getTraccarDeviceId();
                if ($deviceId === null) {
                    continue;
                }

                $checkpoint = $this->entityManager->getRepository(VehicleCheckpoint::class)->findOneBy(['vehicle' => $vehicle]);
                $from = $checkpoint?->getLastDeviceTime();
                if ($from === null) {
                    $from = (new DateTimeImmutable())->sub(new DateInterval('PT30M'));
                }

                $positions = $this->traccarApiClient->getPositions($deviceId, $from);
                $ingested += $this->ingestionService->ingestForVehicle($vehicle, $positions);
            }

            $output->writeln(sprintf('Ciclo traccar completado. Nuevas posiciones: %d', $ingested));

            if ($once) {
                break;
            }
            sleep($sleep);
        } while (true);

        return self::SUCCESS;
    }
}
