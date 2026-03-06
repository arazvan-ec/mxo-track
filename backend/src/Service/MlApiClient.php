<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP client for the Python ML sidecar service.
 */
final class MlApiClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $mlServiceUrl,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Request a prediction from the ML service.
     *
     * @param array<string, mixed> $features
     * @return array<string, mixed>
     */
    public function predict(string $model, array $features): array
    {
        return $this->postJson("/predict/{$model}", $features);
    }

    /**
     * Trigger model training on the ML service.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function train(string $model, array $params = []): array
    {
        return $this->postJson("/train/{$model}", $params);
    }

    /**
     * Check the health of the ML service.
     *
     * @return array<string, mixed>
     */
    public function health(): array
    {
        try {
            $response = $this->httpClient->request('GET', $this->baseUrl('/health'));
            $statusCode = $response->getStatusCode();

            if ($statusCode >= 400) {
                return ['status' => 'error', 'code' => $statusCode];
            }

            /** @var array<string, mixed> */
            return $response->toArray(false);
        } catch (TransportExceptionInterface $e) {
            $this->logger->warning('ML service health check failed: {message}', [
                'message' => $e->getMessage(),
            ]);

            return ['status' => 'unreachable', 'error' => $e->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function postJson(string $path, array $payload): array
    {
        try {
            $response = $this->httpClient->request('POST', $this->baseUrl($path), [
                'json' => $payload,
                'timeout' => 30,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 400) {
                $this->logger->error('ML service returned error {code} for {path}', [
                    'code' => $statusCode,
                    'path' => $path,
                ]);

                return [];
            }

            /** @var array<string, mixed> */
            return $response->toArray(false);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('ML service request failed for {path}: {message}', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function baseUrl(string $path): string
    {
        return rtrim($this->mlServiceUrl, '/') . $path;
    }
}
