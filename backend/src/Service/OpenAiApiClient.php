<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP client for the OpenAI Embeddings API.
 */
final class OpenAiApiClient
{
    private const string EMBEDDINGS_URL = 'https://api.openai.com/v1/embeddings';
    private const string MODEL = 'text-embedding-3-small';
    private const int DIMENSIONS = 1536;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $openaiApiKey,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Get an embedding vector for a single text.
     *
     * @return list<float>|null The embedding vector (1536 dimensions) or null on failure
     */
    public function embed(string $text): ?array
    {
        $result = $this->embedBatch([$text]);

        return $result[0] ?? null;
    }

    /**
     * Get embedding vectors for multiple texts in a single API call.
     *
     * @param list<string> $texts
     *
     * @return list<list<float>> Embedding vectors indexed in the same order as input
     */
    public function embedBatch(array $texts): array
    {
        if (\count($texts) === 0) {
            return [];
        }

        if ($this->openaiApiKey === '') {
            $this->logger->warning('OpenAI API key is not configured');

            return [];
        }

        try {
            $response = $this->httpClient->request('POST', self::EMBEDDINGS_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openaiApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => self::MODEL,
                    'input' => $texts,
                    'dimensions' => self::DIMENSIONS,
                ],
                'timeout' => 60,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                $this->logger->error('OpenAI API error', [
                    'status' => $statusCode,
                    'body' => $response->getContent(false),
                ]);

                return [];
            }

            $data = $response->toArray();

            if (!isset($data['data']) || !\is_array($data['data'])) {
                $this->logger->error('OpenAI API: unexpected response format');

                return [];
            }

            // Sort by index to ensure correct order
            $embeddings = $data['data'];
            usort($embeddings, static fn (array $a, array $b): int => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));

            return array_map(
                static fn (array $item): array => $item['embedding'] ?? [],
                $embeddings,
            );
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('OpenAI API transport error', ['error' => $e->getMessage()]);

            return [];
        } catch (\Throwable $e) {
            $this->logger->error('OpenAI API unexpected error', ['error' => $e->getMessage()]);

            return [];
        }
    }
}
