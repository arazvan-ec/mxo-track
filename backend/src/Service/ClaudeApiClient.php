<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ClaudeApiClient
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $claudeApiKey,
    ) {
    }

    /**
     * Send a completion request to the Claude API.
     *
     * @return array<string, mixed> The full parsed API response
     */
    public function complete(
        string $systemPrompt,
        string $userMessage,
        string $model = 'claude-sonnet-4-20250514',
        float $temperature = 0.3,
        int $maxTokens = 1024,
    ): array {
        $response = $this->httpClient->request('POST', self::API_URL, [
            'headers' => [
                'x-api-key' => $this->claudeApiKey,
                'anthropic-version' => self::API_VERSION,
                'content-type' => 'application/json',
            ],
            'json' => [
                'model' => $model,
                'max_tokens' => $maxTokens,
                'temperature' => $temperature,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $userMessage],
                ],
            ],
        ]);

        /** @var array<string, mixed> */
        return $response->toArray();
    }

    /**
     * Extract the text content from a Claude API response.
     */
    public function extractText(array $response): string
    {
        return $response['content'][0]['text'] ?? '';
    }

    /**
     * Send a completion request with tool definitions (simple, single-turn).
     *
     * @param list<array<string, mixed>> $tools Tool definitions for the API
     * @return array<string, mixed> The full parsed API response
     */
    public function completeWithTools(
        string $systemPrompt,
        string $userMessage,
        array $tools,
        string $model = 'claude-sonnet-4-20250514',
    ): array {
        $response = $this->httpClient->request('POST', self::API_URL, [
            'headers' => [
                'x-api-key' => $this->claudeApiKey,
                'anthropic-version' => self::API_VERSION,
                'content-type' => 'application/json',
            ],
            'json' => [
                'model' => $model,
                'max_tokens' => 1024,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $userMessage],
                ],
                'tools' => $tools,
            ],
        ]);

        /** @var array<string, mixed> */
        return $response->toArray();
    }

    /**
     * Send a raw messages request to the Claude API (multi-turn with tool use loop).
     *
     * @param list<array{role: string, content: mixed}> $messages
     * @param list<array{name: string, description: string, input_schema: array<string, mixed>}> $tools
     * @return array<string, mixed>
     */
    public function sendMessages(array $messages, string $systemPrompt = '', array $tools = [], int $maxTokens = 4096): array
    {
        $payload = [
            'model' => 'claude-sonnet-4-20250514',
            'max_tokens' => $maxTokens,
            'messages' => $messages,
        ];

        if ($systemPrompt !== '') {
            $payload['system'] = $systemPrompt;
        }

        if ($tools !== []) {
            $payload['tools'] = $tools;
        }

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'x-api-key' => $this->claudeApiKey,
                    'anthropic-version' => self::API_VERSION,
                    'content-type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            return $response->toArray();
        } catch (\Throwable $e) {
            $this->logger->error('Claude API error: ' . $e->getMessage());

            return [
                'error' => true,
                'message' => 'Error comunicando con el servicio de IA: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Multi-turn tool use loop: sends messages, executes tools, sends results back until Claude gives a final answer.
     *
     * @param list<array{role: string, content: mixed}> $messages
     * @param list<array{name: string, description: string, input_schema: array<string, mixed>}> $tools
     * @param callable(string, array<string, mixed>): mixed $toolExecutor Called with (toolName, toolInput), must return the tool result
     * @return array{response: string, tools_used: list<string>, error: bool}
     */
    public function completeWithToolLoop(
        array $messages,
        string $systemPrompt,
        array $tools,
        callable $toolExecutor,
        int $maxIterations = 5,
    ): array {
        $toolsUsed = [];

        for ($i = 0; $i < $maxIterations; $i++) {
            $result = $this->sendMessages($messages, $systemPrompt, $tools);

            if (isset($result['error']) && $result['error'] === true) {
                return [
                    'response' => $result['message'] ?? 'Error desconocido',
                    'tools_used' => $toolsUsed,
                    'error' => true,
                ];
            }

            $stopReason = $result['stop_reason'] ?? 'end_turn';
            $content = $result['content'] ?? [];

            if ($stopReason === 'tool_use') {
                // Build assistant message with all content blocks
                $messages[] = ['role' => 'assistant', 'content' => $content];

                // Process each tool_use block
                $toolResults = [];
                foreach ($content as $block) {
                    if (($block['type'] ?? '') === 'tool_use') {
                        $toolName = $block['name'];
                        $toolInput = $block['input'] ?? [];
                        $toolId = $block['id'];
                        $toolsUsed[] = $toolName;

                        try {
                            $toolResult = $toolExecutor($toolName, $toolInput);
                            $toolResults[] = [
                                'type' => 'tool_result',
                                'tool_use_id' => $toolId,
                                'content' => \is_string($toolResult)
                                    ? $toolResult
                                    : json_encode($toolResult, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                            ];
                        } catch (\Throwable $e) {
                            $this->logger->warning('Tool execution error: ' . $e->getMessage(), [
                                'tool' => $toolName,
                            ]);
                            $toolResults[] = [
                                'type' => 'tool_result',
                                'tool_use_id' => $toolId,
                                'content' => json_encode(['error' => $e->getMessage()], JSON_THROW_ON_ERROR),
                                'is_error' => true,
                            ];
                        }
                    }
                }

                $messages[] = ['role' => 'user', 'content' => $toolResults];
                continue;
            }

            // Extract text from content blocks
            $textParts = [];
            foreach ($content as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $textParts[] = $block['text'];
                }
            }

            return [
                'response' => implode("\n", $textParts),
                'tools_used' => array_values(array_unique($toolsUsed)),
                'error' => false,
            ];
        }

        return [
            'response' => 'Se alcanzo el limite de iteraciones de herramientas.',
            'tools_used' => array_values(array_unique($toolsUsed)),
            'error' => true,
        ];
    }
}
