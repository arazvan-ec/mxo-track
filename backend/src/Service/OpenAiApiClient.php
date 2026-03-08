<?php

declare(strict_types=1);

namespace App\Service;

use App\Ai\EmbeddingClientInterface;

/**
 * @deprecated Use App\Ai\EmbeddingClientInterface instead.
 */
final class OpenAiApiClient
{
    public function __construct(
        private readonly EmbeddingClientInterface $embeddingClient,
    ) {
    }

    /** @return list<float>|null */
    public function embed(string $text): ?array
    {
        return $this->embeddingClient->embed($text);
    }

    /**
     * @param list<string> $texts
     * @return list<list<float>>
     */
    public function embedBatch(array $texts): array
    {
        return $this->embeddingClient->embedBatch($texts);
    }
}
