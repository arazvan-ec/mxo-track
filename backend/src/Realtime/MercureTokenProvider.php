<?php
declare(strict_types=1);
namespace App\Realtime;

use Firebase\JWT\JWT;

final readonly class MercureTokenProvider implements SubscriptionTokenProviderInterface
{
    public function __construct(
        private string $subscriberKey,
    ) {
    }

    public function createToken(array $topics, int $ttl = 3600): SubscriptionToken
    {
        $now = time();
        $exp = $now + $ttl;

        $payload = [
            'mercure' => [
                'subscribe' => $topics,
            ],
            'iat' => $now,
            'exp' => $exp,
        ];

        $token = JWT::encode($payload, $this->subscriberKey, 'HS256');

        return new SubscriptionToken(
            token: $token,
            topics: $topics,
            expiry: (new \DateTimeImmutable())->setTimestamp($exp),
        );
    }
}
