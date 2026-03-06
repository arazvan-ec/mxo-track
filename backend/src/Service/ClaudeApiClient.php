<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ClaudeApiClient
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
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
     * Send a completion request with tool definitions.
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
}
