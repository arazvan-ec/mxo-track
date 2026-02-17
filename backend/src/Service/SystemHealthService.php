<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SystemHealthService
{
    public function __construct(
        private readonly TraccarApiClient $traccarApiClient,
        private readonly HttpClientInterface $httpClient,
        private readonly string $mercurePublicUrl,
    ) {
    }

    /** @return array{traccar_ok:bool,mercure_ok:bool} */
    public function check(): array
    {
        $live = $this->checkLive();

        return [
            'traccar_ok' => $live['traccar']['ok'],
            'mercure_ok' => $live['mercure']['ok'],
        ];
    }

    /** @return array<string,mixed> */
    public function checkLive(): array
    {
        $traccarStart = microtime(true);
        $traccarOk = $this->traccarApiClient->canConnect();
        $traccarMs = (int) round((microtime(true) - $traccarStart) * 1000);

        $mercureStart = microtime(true);
        $mercureOk = false;

        try {
            $response = $this->httpClient->request('GET', $this->mercurePublicUrl, [
                'query' => ['topic' => '/health/ping'],
                'timeout' => 2,
            ]);
            $mercureOk = $response->getStatusCode() < 500;
        } catch (ExceptionInterface) {
            $mercureOk = false;
        }

        $mercureMs = (int) round((microtime(true) - $mercureStart) * 1000);

        return [
            'traccar' => [
                'ok' => $traccarOk,
                'latency_ms' => $traccarMs,
            ],
            'mercure' => [
                'ok' => $mercureOk,
                'latency_ms' => $mercureMs,
            ],
        ];
    }
}
