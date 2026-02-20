<?php

declare(strict_types=1);

namespace App\Service;

use Throwable;
use WebSocket\Client;
use WebSocket\Middleware\CloseHandler;
use WebSocket\Middleware\PingResponder;

final class TraccarWebSocketClient
{
    private ?Client $client = null;
    private int $reconnectAttempt = 0;

    public function __construct(
        private readonly TraccarApiClient $traccarApiClient,
        private readonly string $wsUrl,
    ) {
    }

    public function connect(): void
    {
        $this->traccarApiClient->login();
        $cookie = $this->traccarApiClient->getSessionCookie();

        if ($cookie === null) {
            throw new \RuntimeException('Failed to obtain Traccar session cookie');
        }

        $this->client = new Client($this->wsUrl);
        $this->client
            ->addMiddleware(new CloseHandler())
            ->addMiddleware(new PingResponder())
            ->addHeader('Cookie', $cookie)
            ->setTimeout(90);

        $this->client->connect();
        $this->reconnectAttempt = 0;
    }

    /** @return array<string,mixed>|null Parsed JSON message, or null on error/timeout */
    public function receive(): ?array
    {
        if ($this->client === null) {
            return null;
        }

        try {
            $message = $this->client->receive();
            $content = $message->getContent();

            /** @var array<string,mixed>|false $decoded */
            $decoded = json_decode($content, true);

            return is_array($decoded) ? $decoded : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function isConnected(): bool
    {
        if ($this->client === null) {
            return false;
        }

        try {
            return $this->client->isConnected();
        } catch (Throwable) {
            return false;
        }
    }

    public function disconnect(): void
    {
        if ($this->client === null) {
            return;
        }

        try {
            $this->client->disconnect();
        } catch (Throwable) {
            // ignore cleanup errors
        }

        $this->client = null;
    }

    /** Returns seconds to wait before next reconnection attempt (exponential backoff with jitter). */
    public function waitBeforeReconnect(): int
    {
        $this->reconnectAttempt++;
        $base = min(60, (int) (2 ** $this->reconnectAttempt));
        $jitter = (int) ($base * 0.25 * (mt_rand(0, 100) / 100));

        return $base + $jitter;
    }
}
