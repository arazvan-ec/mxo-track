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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('points', null, InputOption::VALUE_REQUIRED, 'Número de posiciones GPS a enviar', '20')
            ->addOption('interval', null, InputOption::VALUE_REQUIRED, 'Segundos entre cada posición', '2')
            ->addOption('ingest', null, InputOption::VALUE_NONE, 'Ingestar posiciones en Symfony al terminar')
            ->addOption('vehicle', null, InputOption::VALUE_REQUIRED, 'Public ID (ULID) del vehículo a simular')
            ->addOption('route-file', null, InputOption::VALUE_OPTIONAL, 'Ruta al JSON de coordenadas. Sin valor usa demo_route_coordinates.json', false)
            ->addOption('segment-delay', null, InputOption::VALUE_REQUIRED, 'Segundos de espera entre segmentos (modo route-file)', '60');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $interval = max(1, (int) $input->getOption('interval'));
        $shouldIngest = (bool) $input->getOption('ingest');

        // 1. Find a vehicle
        $vehiclePublicId = $input->getOption('vehicle');
        $vehicle = $this->findVehicle($vehiclePublicId);
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

        // 4. Bifurcate: route-file mode vs legacy mode
        $routeFileOption = $input->getOption('route-file');

        if ($routeFileOption !== false) {
            $segmentDelay = max(0, (int) $input->getOption('segment-delay'));
            $startTime = $this->executeRouteFileMode($uniqueId, $routeFileOption, $interval, $segmentDelay, $output);
        } else {
            $numPoints = max(1, (int) $input->getOption('points'));
            $startTime = $this->executeLegacyMode($uniqueId, $numPoints, $interval, $output);
        }

        if ($startTime === null) {
            return self::FAILURE;
        }

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

    private function executeRouteFileMode(
        string $uniqueId,
        ?string $routeFilePath,
        int $interval,
        int $segmentDelay,
        OutputInterface $output,
    ): ?DateTimeImmutable {
        // Resolve file path
        if ($routeFilePath === null || $routeFilePath === '') {
            $routeFilePath = $this->projectDir . '/src/DataFixtures/data/demo_route_coordinates.json';
        }

        if (!file_exists($routeFilePath)) {
            $output->writeln(sprintf('<error>Route file not found: %s</error>', $routeFilePath));
            return null;
        }

        $json = file_get_contents($routeFilePath);
        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        $segments = $data['segments'] ?? [];

        if ($segments === []) {
            $output->writeln('<error>No segments found in route file.</error>');
            return null;
        }

        // Sort segments by natural key order ("0-1", "1-2", "2-3", ...)
        uksort($segments, static function (string $a, string $b): int {
            $aStart = (int) explode('-', $a)[0];
            $bStart = (int) explode('-', $b)[0];
            return $aStart <=> $bStart;
        });

        $osmandUrl = $this->getOsmandUrl();
        $segmentKeys = array_keys($segments);
        $totalSegments = \count($segmentKeys);
        $startTime = new DateTimeImmutable();

        $output->writeln(sprintf(
            "\n<info>Modo route-file</info>: %d segmentos, interval=%ds, segment-delay=%ds\n",
            $totalSegments,
            $interval,
            $segmentDelay,
        ));

        foreach ($segmentKeys as $segIndex => $segKey) {
            $segment = $segments[$segKey];
            $fromName = $segment['from']['name'] ?? '?';
            $toName = $segment['to']['name'] ?? '?';
            $route = $segment['route'] ?? '';
            $points = $segment['points'] ?? [];
            $numPoints = \count($points);

            $output->writeln(sprintf(
                '━━━ Segmento <info>%s</info> (%s → %s) — %d puntos',
                $segKey,
                $fromName,
                $toName,
                $numPoints,
            ));
            if ($route !== '') {
                $output->writeln(sprintf('    Ruta: %s', $route));
            }

            foreach ($points as $i => $point) {
                $lat = (float) $point[0];
                $lon = (float) $point[1];

                // Calculate bearing toward next point (or keep last bearing)
                if ($i < $numPoints - 1) {
                    $nextLat = (float) $points[$i + 1][0];
                    $nextLon = (float) $points[$i + 1][1];
                    $bearing = $this->bearing($lat, $lon, $nextLat, $nextLon);
                } else {
                    $bearing = isset($prevBearing) ? $prevBearing : 0.0;
                }
                $prevBearing = $bearing;

                // Simulate speed: 30-50 km/h in knots
                $speedKmh = 30.0 + (20.0 * abs(sin($i * 0.5)));
                $speedKnots = $speedKmh / 1.852;

                $url = sprintf(
                    '%s/?id=%s&lat=%f&lon=%f&speed=%f&bearing=%f&timestamp=%d',
                    rtrim($osmandUrl, '/'),
                    urlencode($uniqueId),
                    $lat,
                    $lon,
                    $speedKnots,
                    $bearing,
                    time(),
                );

                try {
                    $response = $this->httpClient->request('GET', $url);
                    $statusCode = $response->getStatusCode();
                } catch (\Throwable $e) {
                    $statusCode = 0;
                    $output->writeln(sprintf('  <error>Error enviando punto %d: %s</error>', $i + 1, $e->getMessage()));
                    if ($i < $numPoints - 1) {
                        sleep($interval);
                    }
                    continue;
                }

                $output->writeln(sprintf(
                    '  [%d/%d] lat=%.6f lon=%.6f speed=%.1f bearing=%.1f → HTTP %d',
                    $i + 1,
                    $numPoints,
                    $lat,
                    $lon,
                    $speedKnots,
                    $bearing,
                    $statusCode,
                ));

                if ($i < $numPoints - 1) {
                    sleep($interval);
                }
            }

            $output->writeln(sprintf('  ✓ Segmento %s completado.', $segKey));

            // Pause between segments (except after the last one)
            if ($segIndex < $totalSegments - 1 && $segmentDelay > 0) {
                $output->writeln(sprintf("\n  Esperando %ds antes del siguiente segmento...", $segmentDelay));
                for ($remaining = $segmentDelay; $remaining > 0; $remaining--) {
                    if ($remaining % 10 === 0 || $remaining <= 5) {
                        $output->writeln(sprintf('    %ds restantes...', $remaining));
                    }
                    sleep(1);
                }
                $output->writeln('');
            }
        }

        $output->writeln("\nTodos los segmentos completados.");
        return $startTime;
    }

    private function executeLegacyMode(
        string $uniqueId,
        int $numPoints,
        int $interval,
        OutputInterface $output,
    ): DateTimeImmutable {
        $waypoints = $this->madridRoute();
        $osrmRoute = $this->fetchOsrmRoute($waypoints, $output);

        if ($osrmRoute !== null) {
            $points = $this->sampleAlongRoute($osrmRoute, $numPoints);
        } else {
            $points = $this->interpolatePoints($waypoints, $numPoints);
        }

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
        return $startTime;
    }

    private function findVehicle(?string $publicId = null): ?Vehicle
    {
        $repo = $this->entityManager->getRepository(Vehicle::class);

        if ($publicId !== null) {
            return $repo->findOneBy(['publicId' => $publicId, 'isActive' => true]);
        }

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

    /**
     * Decode a Google Encoded Polyline into an array of [lat, lon] pairs.
     *
     * @return list<array{lat: float, lon: float}>
     */
    private function decodePolyline(string $encoded): array
    {
        $points = [];
        $index = 0;
        $len = \strlen($encoded);
        $lat = 0;
        $lon = 0;

        while ($index < $len) {
            foreach (['lat', 'lon'] as $coord) {
                $shift = 0;
                $result = 0;
                do {
                    $byte = \ord($encoded[$index++]) - 63;
                    $result |= ($byte & 0x1F) << $shift;
                    $shift += 5;
                } while ($byte >= 0x20);

                $delta = ($result & 1) ? ~($result >> 1) : ($result >> 1);
                $$coord += $delta;
            }

            $points[] = ['lat' => $lat / 1e5, 'lon' => $lon / 1e5];
        }

        return $points;
    }

    /**
     * Fetch a street-following route from OSRM for the given waypoints.
     * Falls back to null if the request fails.
     *
     * @param list<array{lat: float, lon: float}> $waypoints
     * @return list<array{lat: float, lon: float}>|null
     */
    private function fetchOsrmRoute(array $waypoints, OutputInterface $output): ?array
    {
        $coordinates = implode(';', array_map(
            static fn(array $wp): string => sprintf('%f,%f', $wp['lon'], $wp['lat']),
            $waypoints,
        ));

        $url = sprintf(
            'https://router.project-osrm.org/route/v1/driving/%s?overview=full&geometries=polyline',
            $coordinates,
        );

        try {
            $response = $this->httpClient->request('GET', $url, ['timeout' => 10]);
            $data = $response->toArray();
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<comment>OSRM request failed: %s — using linear interpolation fallback.</comment>', $e->getMessage()));
            return null;
        }

        $geometry = $data['routes'][0]['geometry'] ?? null;
        if (!\is_string($geometry) || $geometry === '') {
            $output->writeln('<comment>OSRM returned no geometry — using linear interpolation fallback.</comment>');
            return null;
        }

        $points = $this->decodePolyline($geometry);
        if (\count($points) < 2) {
            $output->writeln('<comment>OSRM returned too few points — using linear interpolation fallback.</comment>');
            return null;
        }

        $output->writeln(sprintf('<info>OSRM returned %d street-following points.</info>', \count($points)));
        return $points;
    }

    /**
     * Sample numPoints evenly along a polyline, computing speed and bearing for each.
     *
     * @param list<array{lat: float, lon: float}> $routePoints
     * @return list<array{lat: float, lon: float, speed: float, bearing: float}>
     */
    private function sampleAlongRoute(array $routePoints, int $numPoints): array
    {
        // Calculate cumulative distances
        $distances = [0.0];
        for ($i = 1; $i < \count($routePoints); $i++) {
            $distances[] = $distances[$i - 1] + $this->haversine(
                $routePoints[$i - 1]['lat'], $routePoints[$i - 1]['lon'],
                $routePoints[$i]['lat'], $routePoints[$i]['lon'],
            );
        }
        $totalDistance = end($distances);

        $sampled = [];
        $distPerPoint = $totalDistance / max(1, $numPoints - 1);

        for ($p = 0; $p < $numPoints; $p++) {
            $targetDist = min($p * $distPerPoint, $totalDistance);

            // Binary search for the segment containing targetDist
            $lo = 0;
            $hi = \count($distances) - 1;
            while ($lo < $hi - 1) {
                $mid = intdiv($lo + $hi, 2);
                if ($distances[$mid] <= $targetDist) {
                    $lo = $mid;
                } else {
                    $hi = $mid;
                }
            }
            $segIdx = $lo;

            $segLen = $distances[$segIdx + 1] - $distances[$segIdx];
            $t = $segLen > 0 ? ($targetDist - $distances[$segIdx]) / $segLen : 0.0;

            $lat = $routePoints[$segIdx]['lat'] + $t * ($routePoints[$segIdx + 1]['lat'] - $routePoints[$segIdx]['lat']);
            $lon = $routePoints[$segIdx]['lon'] + $t * ($routePoints[$segIdx + 1]['lon'] - $routePoints[$segIdx]['lon']);

            $bearing = $this->bearing(
                $routePoints[$segIdx]['lat'], $routePoints[$segIdx]['lon'],
                $routePoints[$segIdx + 1]['lat'], $routePoints[$segIdx + 1]['lon'],
            );

            // Simulate speed: 30-50 km/h in knots
            $speedKmh = 30.0 + (20.0 * abs(sin($p * 0.5)));
            $speedKnots = $speedKmh / 1.852;

            $sampled[] = [
                'lat' => $lat,
                'lon' => $lon,
                'speed' => $speedKnots,
                'bearing' => $bearing,
            ];
        }

        return $sampled;
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
