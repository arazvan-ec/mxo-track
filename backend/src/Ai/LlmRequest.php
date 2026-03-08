<?php
declare(strict_types=1);
namespace App\Ai;

final readonly class LlmRequest
{
    public function __construct(
        public string $systemPrompt,
        public string $userMessage,
        public string $model = '',
        public int $maxTokens = 1024,
        public float $temperature = 0.3,
    ) {
    }
}
