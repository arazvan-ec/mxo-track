<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;

final class RateLimitedApiClient
{
    /** @var array<string, list<float>> Timestamps of calls per client name */
    private array $callTimestamps = [];

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Execute an API call with rate limiting.
     *
     * Tracks call timestamps per clientName. If the number of calls in the last
     * 60 seconds exceeds maxPerMinute, sleeps until the oldest call expires.
     */
    public function call(callable $apiCall, int $maxPerMinute = 30, string $clientName = 'default'): mixed
    {
        $now = microtime(true);
        $windowStart = $now - 60.0;

        // Clean up expired timestamps
        if (isset($this->callTimestamps[$clientName])) {
            $this->callTimestamps[$clientName] = array_values(
                array_filter(
                    $this->callTimestamps[$clientName],
                    static fn (float $ts): bool => $ts > $windowStart,
                ),
            );
        } else {
            $this->callTimestamps[$clientName] = [];
        }

        // If at the limit, sleep until the oldest call in the window expires
        if (\count($this->callTimestamps[$clientName]) >= $maxPerMinute) {
            $oldestTimestamp = $this->callTimestamps[$clientName][0];
            $sleepSeconds = $oldestTimestamp + 60.0 - $now;

            if ($sleepSeconds > 0) {
                $this->logger->info('Rate limit reached for client "{client}", sleeping {seconds}s', [
                    'client' => $clientName,
                    'seconds' => round($sleepSeconds, 2),
                ]);
                usleep((int) ($sleepSeconds * 1_000_000));
            }

            // Remove the expired entry after sleeping
            array_shift($this->callTimestamps[$clientName]);
        }

        $this->callTimestamps[$clientName][] = microtime(true);

        return $apiCall();
    }
}
