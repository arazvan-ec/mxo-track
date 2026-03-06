<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP client for the Python ML sidecar (FastAPI).
 */
final class MlApiClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Send a POST request to the ML service and return the decoded JSON response.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|null Decoded JSON or null on failure
     */
    public function post(string $path, array $payload): ?array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');

        try {
            $response = $this->httpClient->request('POST', $url, [
                'json' => $payload,
                'timeout' => 30,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                $this->logger->error('ML API returned error', [
                    'url' => $url,
                    'status' => $statusCode,
                    'body' => $response->getContent(false),
                ]);

                return null;
            }

            /** @var array<string, mixed> */
            return $response->toArray();
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('ML API transport error', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (\Throwable $e) {
            $this->logger->error('ML API unexpected error', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
