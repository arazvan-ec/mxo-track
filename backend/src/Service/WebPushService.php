<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PushSubscription;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Sends push notifications to drivers via Web Push.
 *
 * In production this would use a web-push library (e.g. minishlink/web-push).
 * For now it logs notifications and acts as an abstraction layer.
 */
final class WebPushService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly string $vapidPublicKey = '',
        private readonly string $vapidPrivateKey = '',
    ) {}

    public function sendToDriver(User $driver, string $title, string $body, array $data = []): void
    {
        $subscriptions = $this->em->getRepository(PushSubscription::class)->findBy(['user' => $driver]);

        if (\count($subscriptions) === 0) {
            $this->logger->debug('No push subscriptions for driver {email}, skipping notification.', [
                'email' => $driver->getEmail(),
                'title' => $title,
            ]);

            return;
        }

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'timestamp' => time(),
        ], JSON_THROW_ON_ERROR);

        foreach ($subscriptions as $subscription) {
            // In production: use web-push library to send $payload to $subscription->getEndpoint()
            $this->logger->info('Push notification sent to driver {email} at endpoint {endpoint}.', [
                'email' => $driver->getEmail(),
                'endpoint' => $subscription->getEndpoint(),
                'payload' => $payload,
            ]);
        }
    }

    public function getVapidPublicKey(): string
    {
        return $this->vapidPublicKey;
    }
}
