<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class TraccarApiClient
{
    private ?string $cookie = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        private readonly string $username,
        private readonly string $password,
    ) {
    }

    public function login(): void
    {
        $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/').'/api/session', [
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

    public function canConnect(): bool
    {
        try {
            $this->login();
        } catch (TransportExceptionInterface) {
            return false;
        }

        return $this->cookie !== null;
    }

    /** @return list<array<string,mixed>> */
    public function getDevices(): array
    {
        return $this->requestJson('/api/devices');
    }

    /** @return array<string,mixed> */
    public function createDevice(string $name, string $uniqueId): array
    {
        if ($this->cookie === null) {
            $this->login();
        }

        $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/').'/api/devices', [
            'headers' => [
                'Cookie' => $this->cookie ?? '',
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'name' => $name,
                'uniqueId' => $uniqueId,
            ],
        ]);

        /** @var array<string,mixed> */
        return $response->toArray();
    }

    /** @return list<array<string,mixed>> */
    public function getPositions(int $deviceId, ?DateTimeImmutable $from = null): array
    {
        $query = ['deviceId' => (string) $deviceId];
        if ($from !== null) {
            $query['from'] = $from->format(DATE_ATOM);
            $query['to'] = (new DateTimeImmutable())->format(DATE_ATOM);
        }

        return $this->requestJson('/api/positions', $query);
    }

    /** @return list<array<string,mixed>> */
    private function requestJson(string $path, array $query = []): array
    {
        try {
            $response = $this->httpClient->request('GET', rtrim($this->baseUrl, '/').$path, [
                'auth_basic' => [$this->username, $this->password],
                'query' => $query,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                return [];
            }

            $payload = $response->toArray(false);
            return is_array($payload) ? array_values($payload) : [];
        } catch (TransportExceptionInterface|\JsonException) {
            return [];
        }
    }
}
