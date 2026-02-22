<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Customer;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WebhookNotificationService
{
    private const SIGNATURE_ALGORITHM = 'sha256';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $webhookSecret = '',
    ) {
    }

    public function sendWebhook(Customer $customer, string $eventType, array $payload): void
    {
        $webhookUrl = $customer->getWebhookUrl();
        if ($webhookUrl === null || $webhookUrl === '') {
            return;
        }

        $body = json_encode([
            'event' => $eventType,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'data' => $payload,
        ], JSON_THROW_ON_ERROR);

        $signature = $this->computeSignature($body);

        try {
            $this->httpClient->request('POST', $webhookUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-MxoTrack-Signature' => sprintf('%s=%s', self::SIGNATURE_ALGORITHM, $signature),
                    'X-MxoTrack-Event' => $eventType,
                ],
                'body' => $body,
                'timeout' => 10,
            ]);

            $this->logger->info('Webhook sent to {url} for event {event}', [
                'url' => $webhookUrl,
                'event' => $eventType,
                'customer_id' => $customer->getId(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Webhook delivery failed to {url} for event {event}: {error}', [
                'url' => $webhookUrl,
                'event' => $eventType,
                'customer_id' => $customer->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function computeSignature(string $body): string
    {
        $secret = $this->webhookSecret !== '' ? $this->webhookSecret : 'mxo-track-webhook-default';

        return hash_hmac(self::SIGNATURE_ALGORITHM, $body, $secret);
    }
}
