<?php

declare(strict_types=1);

namespace App\Tracking;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class TraccarGpsProvider implements GpsDeviceProviderInterface
{
    private ?string $cookie = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $baseUrl,
        private readonly string $username,
        private readonly string $password,
    ) {
    }

    public function login(): void
    {
        $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/api/session', [
            'body' => [
                'email' => $this->username,
                'password' => $this->password,
            ],
        ]);

        $headers = $response->getHeaders(false);
        $setCookie = $headers['set-cookie'][0] ?? '';
        $this->cookie = $setCookie !== '' ? explode(';', $setCookie)[0] : null;
    }

    public function getSessionCookie(): ?string
    {
        return $this->cookie;
    }

    public function isAvailable(): bool
    {
        try {
            $this->login();
        } catch (TransportExceptionInterface) {
            return false;
        }

        return $this->cookie !== null;
    }

    /** @return list<DeviceInfo> */
    public function getDevices(): array
    {
        $data = $this->requestJson('/api/devices');

        return array_map(
            static fn(array $d) => new DeviceInfo(
                id: (int) ($d['id'] ?? 0),
                name: (string) ($d['name'] ?? ''),
                uniqueId: (string) ($d['uniqueId'] ?? ''),
            ),
            $data,
        );
    }

    public function createDevice(string $name, string $uniqueId): DeviceInfo
    {
        if ($this->cookie === null) {
            $this->login();
        }

        $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/api/devices', [
            'headers' => [
                'Cookie' => $this->cookie ?? '',
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'name' => $name,
                'uniqueId' => $uniqueId,
            ],
        ]);

        /** @var array<string, mixed> */
        $d = $response->toArray();

        return new DeviceInfo(
            id: (int) ($d['id'] ?? 0),
            name: (string) ($d['name'] ?? ''),
            uniqueId: (string) ($d['uniqueId'] ?? ''),
        );
    }

    /** @return list<DevicePosition> */
    public function getPositions(int $deviceId, ?\DateTimeImmutable $since = null): array
    {
        $query = ['deviceId' => (string) $deviceId];
        if ($since !== null) {
            $query['from'] = $since->format(\DATE_ATOM);
            $query['to'] = (new \DateTimeImmutable())->format(\DATE_ATOM);
        }

        $data = $this->requestJson('/api/positions', $query);

        return array_map(
            static fn(array $p) => new DevicePosition(
                latitude: (float) ($p['latitude'] ?? 0.0),
                longitude: (float) ($p['longitude'] ?? 0.0),
                speed: (float) ($p['speed'] ?? 0.0),
                course: (float) ($p['course'] ?? 0.0),
                accuracy: (float) ($p['accuracy'] ?? 0.0),
                deviceTime: new \DateTimeImmutable((string) ($p['deviceTime'] ?? 'now')),
                serverTime: new \DateTimeImmutable((string) ($p['serverTime'] ?? 'now')),
                rawId: isset($p['id']) ? (int) $p['id'] : null,
                deviceId: isset($p['deviceId']) ? (int) $p['deviceId'] : null,
            ),
            $data,
        );
    }

    /** @return list<array<string, mixed>> */
    private function requestJson(string $path, array $query = []): array
    {
        try {
            $response = $this->httpClient->request('GET', rtrim($this->baseUrl, '/') . $path, [
                'auth_basic' => [$this->username, $this->password],
                'query' => $query,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                $this->logger->warning('Traccar API returned error status.', [
                    'path' => $path,
                    'status' => $statusCode,
                ]);

                return [];
            }

            $payload = $response->toArray(false);

            return is_array($payload) ? array_values($payload) : [];
        } catch (TransportExceptionInterface|\JsonException $e) {
            $this->logger->error('Traccar API request failed.', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
