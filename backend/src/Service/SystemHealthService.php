<?php

declare(strict_types=1);

namespace App\Service;

use App\Routing\Coordinate;
use App\Routing\OsrmRoutingEngine;
use App\Tracking\GpsDeviceProviderInterface;
use Doctrine\DBAL\Connection;
use Predis\Client as RedisClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SystemHealthService
{
    public function __construct(
        private readonly GpsDeviceProviderInterface $gpsProvider,
        private readonly HttpClientInterface $httpClient,
        private readonly Connection $connection,
        private readonly RedisClient $redis,
        private readonly string $mercureInternalUrl,
        private readonly OsrmRoutingEngine $osrmEngine,
        private readonly string $vroomUrl = '',
        private readonly string $osrmUrl = '',
    ) {
    }

    /** @return array{traccar_ok:bool,mercure_ok:bool,db_ok:bool,redis_ok:bool,osrm_ok:bool,vroom_ok:bool} */
    public function check(): array
    {
        $live = $this->checkLive();

        return [
            'traccar_ok' => $live['traccar']['ok'],
            'mercure_ok' => $live['mercure']['ok'],
            'db_ok' => $live['database']['ok'],
            'redis_ok' => $live['redis']['ok'],
            'osrm_ok' => $live['osrm']['ok'],
            'vroom_ok' => $live['vroom']['ok'],
        ];
    }

    /** @return array<string,mixed> */
    public function checkLive(): array
    {
        return [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'traccar' => $this->checkTraccar(),
            'mercure' => $this->checkMercure(),
            'osrm' => $this->checkOsrm(),
            'vroom' => $this->checkVroom(),
            'positions' => $this->checkPositionsTable(),
            'disk' => $this->checkDiskUsage(),
            'last_ingestion' => $this->getLastIngestionTimestamp(),
        ];
    }

    /** @return array{ok:bool,latency_ms:int,has_geometry:bool} */
    private function checkOsrm(): array
    {
        $start = microtime(true);
        $ok = false;
        $hasGeometry = false;

        try {
            // Test with two known Madrid coordinates
            $result = $this->osrmEngine->routeWithWaypoints([
                new Coordinate(40.4168, -3.7038),
                new Coordinate(40.4530, -3.6883),
            ]);
            $ok = $result->totalDistanceKm > 0;
            $hasGeometry = $result->geometry !== null;
        } catch (\Throwable) {
        }

        return [
            'ok' => $ok,
            'latency_ms' => (int) round((microtime(true) - $start) * 1000),
            'has_geometry' => $hasGeometry,
        ];
    }

    /** @return array{ok:bool,latency_ms:int} */
    private function checkVroom(): array
    {
        if ($this->vroomUrl === '') {
            return ['ok' => false, 'latency_ms' => 0];
        }

        $start = microtime(true);
        $ok = false;

        try {
            $response = $this->httpClient->request('POST', $this->vroomUrl, [
                'json' => [
                    'vehicles' => [['id' => 0, 'start' => [-3.7038, 40.4168]]],
                    'jobs' => [['id' => 0, 'location' => [-3.6883, 40.4530]]],
                ],
                'timeout' => 5,
            ]);
            $data = $response->toArray();
            $ok = ($data['code'] ?? -1) === 0;
        } catch (\Throwable) {
        }

        return [
            'ok' => $ok,
            'latency_ms' => (int) round((microtime(true) - $start) * 1000),
        ];
    }

    /** @return array{ok:bool,latency_ms:int} */
    private function checkDatabase(): array
    {
        $start = microtime(true);
        $ok = false;

        try {
            $this->connection->fetchOne('SELECT 1');
            $ok = true;
        } catch (\Throwable) {
        }

        return [
            'ok' => $ok,
            'latency_ms' => (int) round((microtime(true) - $start) * 1000),
        ];
    }

    /** @return array{ok:bool,latency_ms:int} */
    private function checkRedis(): array
    {
        $start = microtime(true);
        $ok = false;

        try {
            $pong = $this->redis->ping();
            $ok = (string) $pong === 'PONG' || $pong === true || (string) $pong === '+PONG';
        } catch (\Throwable) {
        }

        return [
            'ok' => $ok,
            'latency_ms' => (int) round((microtime(true) - $start) * 1000),
        ];
    }

    /** @return array{ok:bool,latency_ms:int} */
    private function checkTraccar(): array
    {
        $start = microtime(true);
        $ok = $this->gpsProvider->isAvailable();

        return [
            'ok' => $ok,
            'latency_ms' => (int) round((microtime(true) - $start) * 1000),
        ];
    }

    /** @return array{ok:bool,latency_ms:int} */
    private function checkMercure(): array
    {
        $start = microtime(true);
        $ok = false;

        try {
            $response = $this->httpClient->request('GET', $this->mercureInternalUrl, [
                'query' => ['topic' => '/health/ping'],
                'timeout' => 2,
            ]);
            $ok = $response->getStatusCode() < 500;
        } catch (ExceptionInterface) {
        }

        return [
            'ok' => $ok,
            'latency_ms' => (int) round((microtime(true) - $start) * 1000),
        ];
    }

    /** @return array{row_count:int,warning:bool} */
    private function checkPositionsTable(): array
    {
        try {
            // Use reltuples for fast approximate count (updated by ANALYZE)
            $approx = (int) $this->connection->fetchOne(
                "SELECT COALESCE(reltuples, 0)::bigint FROM pg_class WHERE relname = 'vehicle_positions'"
            );

            // If reltuples is 0 or negative (never analyzed), do a real count only if table is small
            if ($approx <= 0) {
                $approx = (int) $this->connection->fetchOne(
                    'SELECT COUNT(*) FROM vehicle_positions'
                );
            }
        } catch (\Throwable) {
            $approx = 0;
        }

        return [
            'row_count' => $approx,
            'warning' => $approx > 1_000_000,
        ];
    }

    /** @return array{db_size_mb:float} */
    private function checkDiskUsage(): array
    {
        try {
            $sizeBytes = (int) $this->connection->fetchOne(
                "SELECT pg_database_size(current_database())"
            );
            $sizeMb = round($sizeBytes / (1024 * 1024), 2);
        } catch (\Throwable) {
            $sizeMb = 0.0;
        }

        return [
            'db_size_mb' => $sizeMb,
        ];
    }

    /** @return array{timestamp:string|null,seconds_ago:int|null} */
    private function getLastIngestionTimestamp(): array
    {
        try {
            $last = $this->connection->fetchOne(
                'SELECT MAX(server_time) FROM vehicle_positions'
            );

            if ($last === null || $last === false) {
                return ['timestamp' => null, 'seconds_ago' => null];
            }

            $lastTime = new \DateTimeImmutable((string) $last);
            $now = new \DateTimeImmutable();
            $secondsAgo = $now->getTimestamp() - $lastTime->getTimestamp();

            return [
                'timestamp' => $lastTime->format(\DATE_ATOM),
                'seconds_ago' => max(0, $secondsAgo),
            ];
        } catch (\Throwable) {
            return ['timestamp' => null, 'seconds_ago' => null];
        }
    }
}
