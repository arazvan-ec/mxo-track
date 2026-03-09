<?php
declare(strict_types=1);
namespace App\Realtime;

use Psr\Log\LoggerInterface;

final readonly class NullTokenProvider implements SubscriptionTokenProviderInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function createToken(array $topics, int $ttl = 3600): SubscriptionToken
    {
        $this->logger->debug('NullTokenProvider::createToken called.', [
            'topics' => $topics,
        ]);

        return new SubscriptionToken(
            token: 'null-token',
            topics: $topics,
            expiry: (new \DateTimeImmutable())->modify("+{$ttl} seconds"),
        );
    }
}
