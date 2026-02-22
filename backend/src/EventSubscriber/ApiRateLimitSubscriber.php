<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Predis\Client as RedisClient;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiRateLimitSubscriber implements EventSubscriberInterface
{
    private const string REDIS_KEY_PREFIX = 'rate_limit:api:';

    public function __construct(
        private readonly RedisClient $redis,
        private readonly Security $security,
        #[Autowire('%app.rate_limit.api_max_requests%')]
        private readonly int $maxRequests = 60,
        #[Autowire('%app.rate_limit.api_window_seconds%')]
        private readonly int $windowSeconds = 60,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/api/')) {
            return;
        }

        $identifier = $this->resolveIdentifier();
        $key = self::REDIS_KEY_PREFIX . $identifier;
        $now = microtime(true);
        $windowStart = $now - $this->windowSeconds;

        // Sliding window counter using a Redis sorted set
        // Remove entries older than the window
        $this->redis->zremrangebyscore($key, '-inf', (string) $windowStart);

        // Count current requests in window
        $currentCount = $this->redis->zcard($key);

        if ($currentCount >= $this->maxRequests) {
            // Find the oldest entry to calculate Retry-After
            $oldest = $this->redis->zrange($key, 0, 0, ['WITHSCORES' => true]);
            $retryAfter = 1;
            if (!empty($oldest)) {
                $oldestScore = (float) reset($oldest);
                $retryAfter = max(1, (int) ceil($oldestScore + $this->windowSeconds - $now));
            }

            $response = new JsonResponse([
                'error' => [
                    'code' => 'rate_limit_exceeded',
                    'message' => 'Too many requests. Please retry later.',
                ],
            ], 429);
            $response->headers->set('Retry-After', (string) $retryAfter);
            $response->headers->set('X-RateLimit-Limit', (string) $this->maxRequests);
            $response->headers->set('X-RateLimit-Remaining', '0');

            $event->setResponse($response);

            return;
        }

        // Add current request to the sorted set (score = timestamp, member = unique)
        $member = $now . ':' . bin2hex(random_bytes(4));
        $this->redis->zadd($key, [$member => $now]);
        $this->redis->expire($key, $this->windowSeconds + 1);
    }

    private function resolveIdentifier(): string
    {
        $user = $this->security->getUser();
        if ($user instanceof User) {
            return 'user:' . ($user->getId() ?? $user->getUserIdentifier());
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }

        return 'ip:' . trim($ip);
    }
}
