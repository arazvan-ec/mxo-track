<?php
declare(strict_types=1);
namespace App\Ai;

use Psr\Log\LoggerInterface;

final class NullEmbeddingClient implements EmbeddingClientInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function embed(string $text): ?array
    {
        $this->logger->debug('NullEmbeddingClient::embed called.');
        return null;
    }

    public function embedBatch(array $texts): array
    {
        $this->logger->debug('NullEmbeddingClient::embedBatch called.');
        return [];
    }
}
