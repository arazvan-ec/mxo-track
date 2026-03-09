<?php
declare(strict_types=1);
namespace App\Ai;

final readonly class LlmResponse
{
    /**
     * @param array<string, mixed> $rawResponse Full provider response for debugging
     */
    public function __construct(
        public string $content,
        public string $model = '',
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public string $stopReason = '',
        public bool $error = false,
        public array $rawResponse = [],
    ) {
    }
}
