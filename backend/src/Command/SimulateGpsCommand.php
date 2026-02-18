<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Vehicle;
use App\Service\TraccarApiClient;
use App\Service\TraccarIngestionService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:dev:simulate-gps',
    description: 'Simula posiciones GPS enviándolas a Traccar vía OsmAnd y opcionalmente ingesta en Symfony.',
)]
class SimulateGpsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TraccarApiClient $traccarApiClient,
        private readonly TraccarIngestionService $ingestionService,
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('points', null, InputOption::VALUE_REQUIRED, 'Número de posiciones GPS a enviar', '20')
            ->addOption('interval', null, InputOption::VALUE_REQUIRED, 'Segundos entre cada posición', '2')
            ->addOption('ingest', null, InputOption::VALUE_NONE, 'Ingestar posiciones en Symfony al terminar');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $numPoints = max(1, (int) $input->getOption('points'));
        $interval = max(1, (int) $input->getOption('interval'));
        $shouldIngest = (bool) $input->getOption('ingest');

        // 1. Find a vehicle
        $vehicle = $this->findVehicle();
        if ($vehicle === null) {
            $output->writeln('<error>No se encontró ningún Vehicle activo.</error>');
            return self::FAILURE;
        }
        $output->writeln(sprintf('Vehicle: <info>%s</info> (publicId: %s)', $vehicle->getName(), $vehicle->getPublicIdString()));

        // 2. Ensure Traccar device exists
        $uniqueId = 'sim-' . strtolower(str_replace(' ', '-', $vehicle->getName()));
        $device = $this->ensureTraccarDevice($vehicle, $uniqueId, $output);
        if ($device === null) {
            $output->writeln('<error>No se pudo crear/encontrar device en Traccar.</error>');
            return self::FAILURE;
        }
        $traccarDeviceId = (int) $device['id'];
        $output->writeln(sprintf('Traccar device: <info>%s</info> (id: %d, uniqueId: %s)', $device['name'], $traccarDeviceId, $uniqueId));

        // 3. Update Vehicle with Traccar device ID
        $vehicle->setTraccarDeviceId($traccarDeviceId);
        $this->entityManager->flush();
        $output->writeln(sprintf('Vehicle.traccarDeviceId actualizado a <info>%d</info>', $traccarDeviceId));

        // 4. Generate route points and send to OsmAnd
        $waypoints = $this->madridRoute();
        $points = $this->interpolatePoints($waypoints, $numPoints);
        $osmandUrl = $this->getOsmandUrl();

        $output->writeln(sprintf("\nEnviando <info>%d</info> posiciones a OsmAnd (%s)...\n", $numPoints, $osmandUrl));

        $startTime = new DateTimeImmutable();
        foreach ($points as $i => $point) {
            $timestamp = $startTime->modify(sprintf('+%d seconds', $i * $interval));
            $speed = $point['speed'];
            $bearing = $point['bearing'];

            $url = sprintf(
                '%s/?id=%s&lat=%f&lon=%f&speed=%f&bearing=%f&timestamp=%d',
                rtrim($osmandUrl, '/'),
                urlencode($uniqueId),
                $point['lat'],
                $point['lon'],
                $speed,
                $bearing,
                $timestamp->getTimestamp(),
            );

            try {
                $response = $this->httpClient->request('GET', $url);
                $statusCode = $response->getStatusCode();
            } catch (\Throwable $e) {
                $statusCode = 0;
                $output->writeln(sprintf('  <error>Error enviando punto %d: %s</error>', $i + 1, $e->getMessage()));
                continue;
            }

            $output->writeln(sprintf(
                '  [%d/%d] lat=%.6f lon=%.6f speed=%.1f bearing=%.1f → HTTP %d',
                $i + 1,
                $numPoints,
                $point['lat'],
                $point['lon'],
                $speed,
                $bearing,
                $statusCode,
            ));

            if ($i < \count($points) - 1) {
                sleep($interval);
            }
        }

        $output->writeln("\nTodas las posiciones enviadas a Traccar.");

        // 5. Ingest if requested
        if ($shouldIngest) {
            $output->writeln("\nEsperando 3s para que Traccar procese...");
            sleep(3);

            $output->writeln('Ingesting posiciones desde Traccar...');
            $positions = $this->traccarApiClient->getPositions($traccarDeviceId, $startTime);
            $totalCreated = $this->ingestionService->ingestForVehicle($vehicle, $positions);
            $output->writeln(sprintf('<info>%d</info> posiciones ingested en Symfony.', $totalCreated));
        }

        return self::SUCCESS;
    }

    private function findVehicle(): ?Vehicle
    {
        $repo = $this->entityManager->getRepository(Vehicle::class);

        // Prefer a vehicle with "Demo" in its name
        $vehicles = $repo->findBy(['isActive' => true]);
        foreach ($vehicles as $vehicle) {
            if (stripos($vehicle->getName(), 'Demo') !== false) {
                return $vehicle;
            }
        }

        // Fall back to first active vehicle
        return $vehicles[0] ?? null;
    }

    /** @return array<string,mixed>|null */
    private function ensureTraccarDevice(Vehicle $vehicle, string $uniqueId, OutputInterface $output): ?array
    {
        $this->traccarApiClient->login();
        $devices = $this->traccarApiClient->getDevices();

        foreach ($devices as $device) {
            if (($device['uniqueId'] ?? '') === $uniqueId) {
                $output->writeln('Device ya existe en Traccar.');
                return $device;
            }
        }

        $output->writeln('Creando device en Traccar...');
        return $this->traccarApiClient->createDevice($vehicle->getName(), $uniqueId);
    }

    /** @return list<array{lat: float, lon: float}> */
    private function madridRoute(): array
    {
        return [
            ['lat' => 40.4168, 'lon' => -3.7038],  // Puerta del Sol
            ['lat' => 40.4200, 'lon' => -3.7025],  // Gran Vía (este)
            ['lat' => 40.4210, 'lon' => -3.7100],  // Gran Vía (centro)
            ['lat' => 40.4230, 'lon' => -3.7138],  // Gran Vía (oeste)
            ['lat' => 40.4238, 'lon' => -3.7148],  // Plaza de España
            ['lat' => 40.4180, 'lon' => -3.7145],  // Hacia Palacio Real
            ['lat' => 40.4178, 'lon' => -3.7142],  // Palacio Real
            ['lat' => 40.4100, 'lon' => -3.7120],  // Hacia Puerta de Toledo
            ['lat' => 40.4068, 'lon' => -3.7108],  // Puerta de Toledo
            ['lat' => 40.4070, 'lon' => -3.6935],  // Ronda de Toledo → Atocha
            ['lat' => 40.4073, 'lon' => -3.6920],  // Atocha
            ['lat' => 40.4153, 'lon' => -3.6843],  // Retiro (entrada)
            ['lat' => 40.4185, 'lon' => -3.6830],  // Retiro (centro)
            ['lat' => 40.4200, 'lon' => -3.6928],  // Hacia Cibeles
            ['lat' => 40.4198, 'lon' => -3.6933],  // Cibeles
            ['lat' => 40.4168, 'lon' => -3.7038],  // Vuelta a Sol
        ];
    }

    /** @return list<array{lat: float, lon: float, speed: float, bearing: float}> */
    private function interpolatePoints(array $waypoints, int $numPoints): array
    {
        if (\count($waypoints) < 2) {
            return [];
        }

        // Calculate total route distance for even distribution
        $segments = [];
        $totalDistance = 0.0;
        for ($i = 0; $i < \count($waypoints) - 1; $i++) {
            $dist = $this->haversine(
                $waypoints[$i]['lat'], $waypoints[$i]['lon'],
                $waypoints[$i + 1]['lat'], $waypoints[$i + 1]['lon'],
            );
            $segments[] = $dist;
            $totalDistance += $dist;
        }

        $points = [];
        $distPerPoint = $totalDistance / max(1, $numPoints - 1);

        $segmentIndex = 0;
        $distInSegment = 0.0;

        for ($p = 0; $p < $numPoints; $p++) {
            $targetDist = $p * $distPerPoint;
            $accumulated = 0.0;

            // Find the right segment for this point
            $segIdx = 0;
            $remaining = $targetDist;
            while ($segIdx < \count($segments) - 1 && $remaining > $segments[$segIdx]) {
                $remaining -= $segments[$segIdx];
                $segIdx++;
            }

            $segLen = $segments[$segIdx];
            $t = $segLen > 0 ? min(1.0, $remaining / $segLen) : 0.0;

            $lat = $waypoints[$segIdx]['lat'] + $t * ($waypoints[$segIdx + 1]['lat'] - $waypoints[$segIdx]['lat']);
            $lon = $waypoints[$segIdx]['lon'] + $t * ($waypoints[$segIdx + 1]['lon'] - $waypoints[$segIdx]['lon']);

            $bearing = $this->bearing(
                $waypoints[$segIdx]['lat'], $waypoints[$segIdx]['lon'],
                $waypoints[$segIdx + 1]['lat'], $waypoints[$segIdx + 1]['lon'],
            );

            // Simulate speed: 30-50 km/h in knots (Traccar uses knots)
            $speedKmh = 30.0 + (20.0 * abs(sin($p * 0.5)));
            $speedKnots = $speedKmh / 1.852;

            $points[] = [
                'lat' => $lat,
                'lon' => $lon,
                'speed' => $speedKnots,
                'bearing' => $bearing,
            ];
        }

        return $points;
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371000.0; // Earth radius in meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function bearing(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLon = deg2rad($lon2 - $lon1);
        $lat1 = deg2rad($lat1);
        $lat2 = deg2rad($lat2);
        $x = sin($dLon) * cos($lat2);
        $y = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($dLon);
        $bearing = rad2deg(atan2($x, $y));
        return fmod($bearing + 360.0, 360.0);
    }

    private function getOsmandUrl(): string
    {
        $osmandUrl = $_ENV['TRACCAR_OSMAND_URL'] ?? '';
        if ($osmandUrl !== '') {
            return $osmandUrl;
        }

        $baseUrl = $_ENV['TRACCAR_BASE_URL'] ?? 'http://traccar:8082';
        // Replace port 8082 with 5055 for OsmAnd protocol
        return (string) preg_replace('/:\d+$/', ':5055', $baseUrl);
    }
}
