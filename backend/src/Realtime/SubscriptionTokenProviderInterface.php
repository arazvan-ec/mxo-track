<?php
declare(strict_types=1);
namespace App\Realtime;

interface SubscriptionTokenProviderInterface
{
    /**
     * Create a time-limited subscription token for the given topics.
     *
     * @param list<string> $topics  Topic IRIs the subscriber may listen to
     * @param int          $ttl     Token lifetime in seconds
     */
    public function createToken(array $topics, int $ttl = 3600): SubscriptionToken;
}
