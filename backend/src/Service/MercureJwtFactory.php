<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Security\TopicResolver;
use Firebase\JWT\JWT;

class MercureJwtFactory
{
    public function __construct(
        private readonly TopicResolver $topicResolver,
        private readonly string $subscriberKey,
        private readonly int $ttlSeconds,
    ) {
    }

    /**
     * @param list<string> $allowedVehiclePublicIds
     */
    public function createSubscriberToken(User $user, array $allowedVehiclePublicIds = []): string
    {
        $now = time();
        $topics = $this->topicResolver->resolveForUser($user, $allowedVehiclePublicIds);

        $payload = [
            'mercure' => [
                'subscribe' => $topics,
            ],
            'sub' => sprintf('user:%s', $user->getId()),
            'role' => implode(',', $user->getRoles()),
            'iat' => $now,
            'exp' => $now + $this->ttlSeconds,
        ];

        return JWT::encode($payload, $this->subscriberKey, 'HS256');
    }
}
