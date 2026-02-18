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
        if ($this->cookie === null) {
            $this->login();
        }

        try {
            $response = $this->httpClient->request('GET', rtrim($this->baseUrl, '/').$path, [
                'headers' => $this->cookie ? ['Cookie' => $this->cookie] : [],
                'query' => $query,
            ]);

            $payload = $response->toArray(false);
            return is_array($payload) ? array_values($payload) : [];
        } catch (TransportExceptionInterface) {
            $this->cookie = null;
            return [];
        }
    }
}
