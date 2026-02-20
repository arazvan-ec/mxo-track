<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Vehicle;
use App\Service\TraccarApiClient;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:smoke:traccar-once', description: 'Smoke test CLI: login Traccar y lectura mínima de posiciones.')]
class SmokeTraccarOnceCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TraccarApiClient $traccarApiClient,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $vehicles = $this->entityManager->getRepository(Vehicle::class)->findBy(['isActive' => true]);
        $withDevice = array_values(array_filter($vehicles, static fn (Vehicle $v): bool => $v->getTraccarDeviceId() !== null));

        $output->writeln(sprintf('smoke.traccar.active_vehicles=%d', count($vehicles)));
        $output->writeln(sprintf('smoke.traccar.mapped_vehicles=%d', count($withDevice)));

        $devices = $this->traccarApiClient->getDevices();
        $output->writeln(sprintf('smoke.traccar.devices_visible=%d', count($devices)));

        if ($withDevice === []) {
            $output->writeln('smoke.traccar.positions_checked=0');
            return self::SUCCESS;
        }

        $deviceId = $withDevice[0]->getTraccarDeviceId();
        $from = (new DateTimeImmutable())->sub(new DateInterval('PT10M'));
        $positions = $this->traccarApiClient->getPositions((int) $deviceId, $from);
        $output->writeln(sprintf('smoke.traccar.positions_checked=%d', count($positions)));

        return self::SUCCESS;
    }
}
