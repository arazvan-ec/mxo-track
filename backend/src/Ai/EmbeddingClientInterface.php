<?php
declare(strict_types=1);
namespace App\Ai;

interface EmbeddingClientInterface
{
    /** @return list<float>|null */
    public function embed(string $text): ?array;

    /**
     * @param list<string> $texts
     * @return list<list<float>>
     */
    public function embedBatch(array $texts): array;
}
