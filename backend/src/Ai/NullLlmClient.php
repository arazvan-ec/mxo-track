<?php
declare(strict_types=1);
namespace App\Ai;

use Psr\Log\LoggerInterface;

final class NullLlmClient implements LlmClientInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function complete(LlmRequest $request): LlmResponse
    {
        $this->logger->debug('NullLlmClient::complete called.');
        return new LlmResponse(content: '');
    }

    public function completeWithToolLoop(
        array $messages,
        string $systemPrompt,
        array $tools,
        callable $toolExecutor,
        int $maxIterations = 5,
    ): LlmResponse {
        $this->logger->debug('NullLlmClient::completeWithToolLoop called.');
        return new LlmResponse(content: '');
    }

    public function sendMessages(
        array $messages,
        string $systemPrompt = '',
        array $tools = [],
        int $maxTokens = 4096,
    ): LlmResponse {
        $this->logger->debug('NullLlmClient::sendMessages called.');
        return new LlmResponse(content: '');
    }
}
