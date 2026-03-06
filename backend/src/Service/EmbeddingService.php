<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Manages vector embeddings for semantic search using pgvector.
 *
 * Stores embeddings in the `ml_embedding` table and performs nearest-neighbor
 * search using cosine distance (<=> operator).
 */
final class EmbeddingService
{
    private const string TABLE = 'ml_embedding';

    public function __construct(
        private readonly OpenAiApiClient $openAiClient,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Generate an embedding for the given text and store it in the ml_embedding table.
     */
    public function embedAndStore(string $entityType, int $entityId, string $text): void
    {
        $embedding = $this->openAiClient->embed($text);
        if ($embedding === null || \count($embedding) === 0) {
            $this->logger->warning('Failed to generate embedding', [
                'entityType' => $entityType,
                'entityId' => $entityId,
            ]);

            return;
        }

        $vectorString = $this->vectorToString($embedding);

        $conn = $this->em->getConnection();

        // Upsert: insert or update on conflict
        $sql = <<<'SQL'
            INSERT INTO ml_embedding (entity_type, entity_id, embedding, text_content, updated_at)
            VALUES (:entity_type, :entity_id, :embedding, :text_content, NOW())
            ON CONFLICT (entity_type, entity_id)
            DO UPDATE SET embedding = EXCLUDED.embedding,
                          text_content = EXCLUDED.text_content,
                          updated_at = NOW()
            SQL;

        $conn->executeStatement($sql, [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'embedding' => $vectorString,
            'text_content' => $text,
        ]);
    }

    /**
     * Search for the nearest embeddings to a query string.
     *
     * @return list<array{entity_type: string, entity_id: int, text_content: string, similarity: float}>
     */
    public function search(string $query, string $entityType, int $limit = 10): array
    {
        $queryEmbedding = $this->openAiClient->embed($query);
        if ($queryEmbedding === null || \count($queryEmbedding) === 0) {
            $this->logger->warning('Failed to generate query embedding');

            return [];
        }

        $vectorString = $this->vectorToString($queryEmbedding);

        $conn = $this->em->getConnection();

        // Use cosine distance operator (<=>). Lower distance = higher similarity.
        // Similarity = 1 - cosine_distance.
        $sql = <<<'SQL'
            SELECT entity_type,
                   entity_id,
                   text_content,
                   1 - (embedding <=> :query_embedding::vector) AS similarity
            FROM ml_embedding
            WHERE entity_type = :entity_type
            ORDER BY embedding <=> :query_embedding::vector
            LIMIT :max_results
            SQL;

        $result = $conn->executeQuery($sql, [
            'query_embedding' => $vectorString,
            'entity_type' => $entityType,
            'max_results' => $limit,
        ]);

        $rows = [];
        foreach ($result->fetchAllAssociative() as $row) {
            $rows[] = [
                'entity_type' => (string) $row['entity_type'],
                'entity_id' => (int) $row['entity_id'],
                'text_content' => (string) $row['text_content'],
                'similarity' => round((float) $row['similarity'], 6),
            ];
        }

        return $rows;
    }

    /**
     * Convert a float array to a pgvector-compatible string: "[0.1,0.2,...]".
     *
     * @param list<float> $vector
     */
    private function vectorToString(array $vector): string
    {
        return '[' . implode(',', array_map(static fn (float $v): string => (string) $v, $vector)) . ']';
    }
}
