<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Customer;
use App\Entity\WebhookEndpoint;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Dispatches webhook events to all registered WebhookEndpoint entities for a customer.
 */
final class WebhookDispatcher
{
    private const SIGNATURE_ALGORITHM = 'sha256';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function dispatch(Customer $customer, string $eventType, array $payload): void
    {
        $endpoints = $this->entityManager->getRepository(WebhookEndpoint::class)->findBy([
            'customer' => $customer,
            'isActive' => true,
        ]);

        foreach ($endpoints as $endpoint) {
            if (!$endpoint->supportsEvent($eventType)) {
                continue;
            }

            $this->sendToEndpoint($endpoint, $eventType, $payload);
        }
    }

    private function sendToEndpoint(WebhookEndpoint $endpoint, string $eventType, array $payload): void
    {
        $body = json_encode([
            'event' => $eventType,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'data' => $payload,
        ], \JSON_THROW_ON_ERROR);

        $signature = hash_hmac(self::SIGNATURE_ALGORITHM, $body, $endpoint->getSecret());

        try {
            $this->httpClient->request('POST', $endpoint->getUrl(), [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-MxoTrack-Signature' => sprintf('%s=%s', self::SIGNATURE_ALGORITHM, $signature),
                    'X-MxoTrack-Event' => $eventType,
                ],
                'body' => $body,
                'timeout' => 10,
            ]);

            $this->logger->info('Webhook dispatched to {url} for event {event}', [
                'url' => $endpoint->getUrl(),
                'event' => $eventType,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Webhook dispatch failed to {url} for event {event}: {error}', [
                'url' => $endpoint->getUrl(),
                'event' => $eventType,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
