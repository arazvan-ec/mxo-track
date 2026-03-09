<?php
declare(strict_types=1);
namespace App\Realtime;

final readonly class SubscriptionToken
{
    /**
     * @param string              $token   Encoded JWT or opaque token string
     * @param list<string>        $topics  Topics this token authorizes subscription to
     * @param \DateTimeImmutable  $expiry  When the token expires
     */
    public function __construct(
        public string $token,
        public array $topics,
        public \DateTimeImmutable $expiry,
    ) {
    }
}
