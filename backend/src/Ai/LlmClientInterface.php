<?php
declare(strict_types=1);
namespace App\Ai;

interface LlmClientInterface
{
    public function complete(LlmRequest $request): LlmResponse;

    /**
     * @param list<array{role: string, content: string}> $messages
     * @param list<ToolDefinition> $tools
     * @param callable(string, array): mixed $toolExecutor fn(toolName, toolInput) => result
     */
    public function completeWithToolLoop(
        array $messages,
        string $systemPrompt,
        array $tools,
        callable $toolExecutor,
        int $maxIterations = 5,
    ): LlmResponse;

    /**
     * Raw messages API for multi-turn conversations.
     *
     * @param list<array<string, mixed>> $messages
     * @param list<ToolDefinition> $tools
     */
    public function sendMessages(
        array $messages,
        string $systemPrompt = '',
        array $tools = [],
        int $maxTokens = 4096,
    ): LlmResponse;
}
