<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\ApiKey;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Rate-limits Public API v1 requests using a Redis sliding-window counter per API key.
 * Falls back to in-memory tracking when Redis is unavailable.
 */
final class ApiRateLimitSubscriber implements EventSubscriberInterface
{
    private readonly ?\Redis $redis;

    /** @var array<string, array{count: int, reset: int}> In-memory fallback */
    private array $inMemoryCounters = [];

    public function __construct(
        private readonly string $redisUrl = '',
    ) {
        $this->redis = $this->connectRedis();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Run after authentication but before controller (priority < firewall listener)
            KernelEvents::REQUEST => ['onKernelRequest', 8],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Only apply to API v1 routes
        if (!str_starts_with($request->getPathInfo(), '/api/v1')) {
            return;
        }

        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('_api_key');
        if (!$apiKey instanceof ApiKey) {
            return;
        }

        $limit = $apiKey->getRateLimitPerMinute();
        $keyId = $apiKey->getId() ?? 'unknown';
        $redisKey = 'api_rate:' . $keyId;
        $windowSeconds = 60;

        $current = $this->incrementCounter($redisKey, $windowSeconds);

        // Always add rate limit headers
        $request->attributes->set('_rate_limit', $limit);
        $request->attributes->set('_rate_remaining', max(0, $limit - $current));

        if ($current > $limit) {
            $response = new JsonResponse([
                'error' => [
                    'code' => 'rate_limit_exceeded',
                    'message' => sprintf('Rate limit of %d requests per minute exceeded.', $limit),
                ],
            ], Response::HTTP_TOO_MANY_REQUESTS);

            $response->headers->set('X-RateLimit-Limit', (string) $limit);
            $response->headers->set('X-RateLimit-Remaining', '0');
            $response->headers->set('Retry-After', '60');

            $event->setResponse($response);
        }
    }

    private function incrementCounter(string $key, int $windowSeconds): int
    {
        if ($this->redis !== null) {
            return $this->incrementRedis($key, $windowSeconds);
        }

        return $this->incrementInMemory($key, $windowSeconds);
    }

    private function incrementRedis(string $key, int $windowSeconds): int
    {
        try {
            $current = $this->redis->incr($key);
            if ($current === 1) {
                $this->redis->expire($key, $windowSeconds);
            }

            return $current;
        } catch (\Throwable) {
            return $this->incrementInMemory($key, $windowSeconds);
        }
    }

    private function incrementInMemory(string $key, int $windowSeconds): int
    {
        $now = time();

        if (!isset($this->inMemoryCounters[$key]) || $this->inMemoryCounters[$key]['reset'] <= $now) {
            $this->inMemoryCounters[$key] = [
                'count' => 0,
                'reset' => $now + $windowSeconds,
            ];
        }

        return ++$this->inMemoryCounters[$key]['count'];
    }

    private function connectRedis(): ?\Redis
    {
        if ($this->redisUrl === '' || !class_exists(\Redis::class)) {
            return null;
        }

        try {
            $parsed = parse_url($this->redisUrl);
            $redis = new \Redis();
            $redis->connect(
                $parsed['host'] ?? '127.0.0.1',
                $parsed['port'] ?? 6379,
                2.0,
            );

            if (isset($parsed['pass'])) {
                $redis->auth($parsed['pass']);
            }

            return $redis;
        } catch (\Throwable) {
            return null;
        }
    }
}
