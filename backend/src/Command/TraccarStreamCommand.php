<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Vehicle;
use App\Entity\VehicleCheckpoint;
use App\Service\TraccarApiClient;
use App\Service\TraccarIngestionService;
use App\Service\TraccarWebSocketClient;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:traccar:stream', description: 'Ingest Traccar positions via polling or WebSocket.')]
class TraccarStreamCommand extends Command
{
    private const DEVICE_MAP_REFRESH_SECONDS = 60;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TraccarApiClient $traccarApiClient,
        private readonly TraccarIngestionService $ingestionService,
        private readonly TraccarWebSocketClient $webSocketClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('mode', null, InputOption::VALUE_REQUIRED, 'Ingestion mode: poll or ws', 'poll')
            ->addOption('once', null, InputOption::VALUE_NONE, 'Run one polling cycle and exit (poll mode only)')
            ->addOption('sleep', null, InputOption::VALUE_REQUIRED, 'Seconds between polling cycles', '5');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $mode = (string) $input->getOption('mode');

        return match ($mode) {
            'poll' => $this->runPollingLoop($input, $output),
            'ws' => $this->runWebSocketLoop($output),
            default => $this->invalidMode($output, $mode),
        };
    }

    private function runPollingLoop(InputInterface $input, OutputInterface $output): int
    {
        $once = (bool) $input->getOption('once');
        $sleep = max(1, (int) $input->getOption('sleep'));

        do {
            $ingested = $this->pollOneCycle($output);
            $output->writeln(sprintf('[poll] Cycle complete. New positions: %d', $ingested));

            if ($once) {
                break;
            }
            sleep($sleep);
        } while (true);

        return self::SUCCESS;
    }

    private function runWebSocketLoop(OutputInterface $output): int
    {
        // Backfill any gap before switching to WebSocket
        $backfilled = $this->pollOneCycle($output);
        $output->writeln(sprintf('[ws] Initial backfill complete. Positions: %d', $backfilled));

        while (true) {
            $deviceMap = $this->buildDeviceMap();
            $output->writeln(sprintf('[ws] Device map: %d vehicles', count($deviceMap)));

            try {
                $this->webSocketClient->connect();
                $output->writeln('[ws] Connected to Traccar WebSocket');
            } catch (\Throwable $e) {
                $output->writeln(sprintf('[ws] Connection failed: %s', $e->getMessage()));
                $this->reconnectWithBackoff($output);
                continue;
            }

            $lastMapRefresh = time();

            while ($this->webSocketClient->isConnected()) {
                $message = $this->webSocketClient->receive();

                if ($message === null) {
                    $output->writeln('[ws] Receive returned null — connection lost or timeout');
                    break;
                }

                $ingested = $this->processWsMessage($message, $deviceMap, $output);
                if ($ingested > 0) {
                    $output->writeln(sprintf('[ws] Ingested %d position(s)', $ingested));
                }

                // Refresh device map periodically
                if (time() - $lastMapRefresh >= self::DEVICE_MAP_REFRESH_SECONDS) {
                    $this->entityManager->clear();
                    $deviceMap = $this->buildDeviceMap();
                    $lastMapRefresh = time();
                    $output->writeln(sprintf('[ws] Device map refreshed: %d vehicles', count($deviceMap)));
                }
            }

            $this->webSocketClient->disconnect();

            // Backfill gap accumulated during disconnect
            $backfilled = $this->pollOneCycle($output);
            $output->writeln(sprintf('[ws] Backfill after disconnect: %d positions', $backfilled));

            $this->reconnectWithBackoff($output);
        }
    }

    /** Runs one polling cycle across all active vehicles. Returns total ingested positions. */
    private function pollOneCycle(OutputInterface $output): int
    {
        $this->entityManager->clear();
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

        return $ingested;
    }

    /**
     * Builds a map of traccarDeviceId → Vehicle for all active vehicles.
     * @return array<int, Vehicle>
     */
    private function buildDeviceMap(): array
    {
        $this->entityManager->clear();
        $vehicles = $this->entityManager->getRepository(Vehicle::class)->findBy(['isActive' => true]);
        $map = [];

        foreach ($vehicles as $vehicle) {
            $deviceId = $vehicle->getTraccarDeviceId();
            if ($deviceId !== null) {
                $map[$deviceId] = $vehicle;
            }
        }

        return $map;
    }

    /**
     * Processes a single WebSocket message from Traccar.
     * @param array<string,mixed> $message
     * @param array<int, Vehicle> $deviceMap
     */
    private function processWsMessage(array $message, array &$deviceMap, OutputInterface $output): int
    {
        /** @var list<array<string,mixed>> $positions */
        $positions = $message['positions'] ?? [];
        if ($positions === []) {
            return 0;
        }

        // Group positions by deviceId
        /** @var array<int, list<array<string,mixed>>> $grouped */
        $grouped = [];
        foreach ($positions as $position) {
            $deviceId = (int) ($position['deviceId'] ?? 0);
            if ($deviceId === 0) {
                continue;
            }
            $grouped[$deviceId][] = $position;
        }

        $totalIngested = 0;

        foreach ($grouped as $deviceId => $devicePositions) {
            $vehicle = $deviceMap[$deviceId] ?? null;
            if ($vehicle === null) {
                continue;
            }

            // Re-attach vehicle if entity manager was cleared
            if (!$this->entityManager->contains($vehicle)) {
                $vehicle = $this->entityManager->find(Vehicle::class, $vehicle->getId());
                if ($vehicle === null) {
                    unset($deviceMap[$deviceId]);
                    continue;
                }
                $deviceMap[$deviceId] = $vehicle;
            }

            $totalIngested += $this->ingestionService->ingestForVehicle($vehicle, $devicePositions);
        }

        return $totalIngested;
    }

    private function reconnectWithBackoff(OutputInterface $output): void
    {
        $wait = $this->webSocketClient->waitBeforeReconnect();
        $output->writeln(sprintf('[ws] Reconnecting in %ds...', $wait));
        sleep($wait);
    }

    private function invalidMode(OutputInterface $output, string $mode): int
    {
        $output->writeln(sprintf('<error>Invalid mode "%s". Use "poll" or "ws".</error>', $mode));

        return self::FAILURE;
    }
}
