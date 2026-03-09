<?php
declare(strict_types=1);
namespace App\Ai;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OpenAiEmbeddingClient implements EmbeddingClientInterface
{
    private const API_URL = 'https://api.openai.com/v1/embeddings';
    private const MODEL = 'text-embedding-3-small';
    private const DIMENSIONS = 1536;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $openaiApiKey,
    ) {
    }

    public function embed(string $text): ?array
    {
        $results = $this->embedBatch([$text]);
        return $results[0] ?? null;
    }

    public function embedBatch(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openaiApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => self::MODEL,
                    'input' => $texts,
                    'dimensions' => self::DIMENSIONS,
                ],
                'timeout' => 30,
            ]);

            $data = $response->toArray();
            $embeddings = $data['data'] ?? [];

            usort($embeddings, static fn(array $a, array $b) => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));

            return array_map(
                static fn(array $item) => $item['embedding'] ?? [],
                $embeddings,
            );
        } catch (\Throwable $e) {
            $this->logger->error('OpenAI embedding request failed.', [
                'textCount' => \count($texts),
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
