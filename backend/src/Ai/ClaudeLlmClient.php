<?php
declare(strict_types=1);
namespace App\Ai;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ClaudeLlmClient implements LlmClientInterface
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';
    private const DEFAULT_MODEL = 'claude-sonnet-4-20250514';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $claudeApiKey,
    ) {
    }

    public function complete(LlmRequest $request): LlmResponse
    {
        $model = $request->model !== '' ? $request->model : self::DEFAULT_MODEL;

        $payload = [
            'model' => $model,
            'max_tokens' => $request->maxTokens,
            'temperature' => $request->temperature,
            'messages' => [
                ['role' => 'user', 'content' => $request->userMessage],
            ],
        ];

        if ($request->systemPrompt !== '') {
            $payload['system'] = $request->systemPrompt;
        }

        return $this->doRequest($payload);
    }

    public function completeWithToolLoop(
        array $messages,
        string $systemPrompt,
        array $tools,
        callable $toolExecutor,
        int $maxIterations = 5,
    ): LlmResponse {
        $anthropicTools = array_map(static fn(ToolDefinition $t) => [
            'name' => $t->name,
            'description' => $t->description,
            'input_schema' => $t->inputSchema,
        ], $tools);

        $currentMessages = $messages;
        $toolsUsed = [];

        for ($i = 0; $i < $maxIterations; $i++) {
            $payload = [
                'model' => self::DEFAULT_MODEL,
                'max_tokens' => 4096,
                'messages' => $currentMessages,
            ];

            if ($systemPrompt !== '') {
                $payload['system'] = $systemPrompt;
            }

            if ($anthropicTools !== []) {
                $payload['tools'] = $anthropicTools;
            }

            $response = $this->doRequest($payload);

            if ($response->error || $response->stopReason !== 'tool_use') {
                return new LlmResponse(
                    content: $response->content,
                    model: $response->model,
                    inputTokens: $response->inputTokens,
                    outputTokens: $response->outputTokens,
                    stopReason: $response->stopReason,
                    rawResponse: array_merge($response->rawResponse, ['tools_used' => $toolsUsed]),
                );
            }

            // Process tool calls
            $rawResponse = $response->rawResponse;
            $contentBlocks = $rawResponse['content'] ?? [];
            $currentMessages[] = ['role' => 'assistant', 'content' => $contentBlocks];

            $toolResults = [];
            foreach ($contentBlocks as $block) {
                if (($block['type'] ?? '') === 'tool_use') {
                    $toolName = $block['name'] ?? '';
                    $toolInput = $block['input'] ?? [];
                    $toolsUsed[] = $toolName;

                    try {
                        $result = $toolExecutor($toolName, $toolInput);
                        $toolResults[] = [
                            'type' => 'tool_result',
                            'tool_use_id' => $block['id'],
                            'content' => \is_string($result) ? $result : json_encode($result),
                        ];
                    } catch (\Throwable $e) {
                        $toolResults[] = [
                            'type' => 'tool_result',
                            'tool_use_id' => $block['id'],
                            'content' => 'Error: ' . $e->getMessage(),
                            'is_error' => true,
                        ];
                    }
                }
            }

            $currentMessages[] = ['role' => 'user', 'content' => $toolResults];
        }

        return new LlmResponse(
            content: '',
            error: true,
            rawResponse: ['tools_used' => $toolsUsed, 'error' => 'Max iterations reached'],
        );
    }

    public function sendMessages(
        array $messages,
        string $systemPrompt = '',
        array $tools = [],
        int $maxTokens = 4096,
    ): LlmResponse {
        $payload = [
            'model' => self::DEFAULT_MODEL,
            'max_tokens' => $maxTokens,
            'messages' => $messages,
        ];

        if ($systemPrompt !== '') {
            $payload['system'] = $systemPrompt;
        }

        if ($tools !== []) {
            $payload['tools'] = array_map(static fn(ToolDefinition $t) => [
                'name' => $t->name,
                'description' => $t->description,
                'input_schema' => $t->inputSchema,
            ], $tools);
        }

        return $this->doRequest($payload);
    }

    private function doRequest(array $payload): LlmResponse
    {
        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'x-api-key' => $this->claudeApiKey,
                    'anthropic-version' => self::API_VERSION,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => 120,
            ]);

            $data = $response->toArray();

            $text = '';
            foreach ($data['content'] ?? [] as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $text .= $block['text'] ?? '';
                }
            }

            return new LlmResponse(
                content: $text,
                model: $data['model'] ?? '',
                inputTokens: $data['usage']['input_tokens'] ?? 0,
                outputTokens: $data['usage']['output_tokens'] ?? 0,
                stopReason: $data['stop_reason'] ?? '',
                rawResponse: $data,
            );
        } catch (\Throwable $e) {
            $this->logger->error('Claude API request failed.', [
                'error' => $e->getMessage(),
            ]);

            return new LlmResponse(
                content: '',
                error: true,
                rawResponse: ['error' => true, 'message' => $e->getMessage()],
            );
        }
    }
}
