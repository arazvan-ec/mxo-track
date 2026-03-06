<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP client for the ML sidecar (FastAPI) service.
 */
final class MlApiClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Call a prediction endpoint on the ML service.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null Null on failure (caller should use fallback).
     */
    public function predict(string $endpoint, array $payload): ?array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');

        try {
            $response = $this->httpClient->request('POST', $url, [
                'json' => $payload,
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->warning('ML service returned non-200', [
                    'endpoint' => $endpoint,
                    'status' => $response->getStatusCode(),
                ]);

                return null;
            }

            return $response->toArray();
        } catch (\Throwable $e) {
            $this->logger->error('ML service call failed', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Trigger model training.
     *
     * @return array<string, mixed>|null Null on failure.
     */
    public function train(string $endpoint): ?array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');

        try {
            $response = $this->httpClient->request('POST', $url, [
                'timeout' => 120,
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->warning('ML training returned non-200', [
                    'endpoint' => $endpoint,
                    'status' => $response->getStatusCode(),
                ]);

                return null;
            }

            return $response->toArray();
        } catch (\Throwable $e) {
            $this->logger->error('ML training call failed', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
