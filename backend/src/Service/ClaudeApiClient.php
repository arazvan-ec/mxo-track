<?php

declare(strict_types=1);

namespace App\Service;

use App\Ai\LlmClientInterface;
use App\Ai\LlmRequest;
use App\Ai\LlmResponse;
use App\Ai\ToolDefinition;

/**
 * @deprecated Use App\Ai\LlmClientInterface instead.
 */
final class ClaudeApiClient
{
    public function __construct(
        private readonly LlmClientInterface $llmClient,
    ) {
    }

    /** @return array<string, mixed> */
    public function complete(
        string $systemPrompt,
        string $userMessage,
        string $model = '',
        float $temperature = 0.3,
        int $maxTokens = 1024,
    ): array {
        $response = $this->llmClient->complete(new LlmRequest(
            $systemPrompt,
            $userMessage,
            $model,
            $maxTokens,
            $temperature,
        ));

        return $response->rawResponse;
    }

    public function extractText(array $response): string
    {
        return $response['content'][0]['text'] ?? '';
    }

    /** @return array<string, mixed> */
    public function sendMessages(array $messages, string $systemPrompt = '', array $tools = [], int $maxTokens = 4096): array
    {
        $toolDefs = array_map(
            static fn(array $t) => new ToolDefinition($t['name'], $t['description'], $t['input_schema']),
            $tools,
        );

        $response = $this->llmClient->sendMessages($messages, $systemPrompt, $toolDefs, $maxTokens);

        if ($response->error) {
            return ['error' => true, 'message' => $response->rawResponse['message'] ?? 'Unknown error'];
        }

        return $response->rawResponse;
    }

    /**
     * @return array{response: string, tools_used: list<string>, error: bool}
     */
    public function completeWithToolLoop(
        array $messages,
        string $systemPrompt,
        array $tools,
        callable $toolExecutor,
        int $maxIterations = 5,
    ): array {
        $toolDefs = array_map(
            static fn(array $t) => new ToolDefinition($t['name'], $t['description'], $t['input_schema']),
            $tools,
        );

        $response = $this->llmClient->completeWithToolLoop($messages, $systemPrompt, $toolDefs, $toolExecutor, $maxIterations);

        return [
            'response' => $response->content,
            'tools_used' => $response->rawResponse['tools_used'] ?? [],
            'error' => $response->error,
        ];
    }
}
