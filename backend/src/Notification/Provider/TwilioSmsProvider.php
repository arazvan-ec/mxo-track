<?php

declare(strict_types=1);

namespace App\Notification\Provider;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class TwilioSmsProvider implements SmsProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $accountSid,
        private readonly string $authToken,
        private readonly string $fromNumber,
    ) {
    }

    public function sendSms(string $phoneNumber, string $message): bool
    {
        if ($this->accountSid === '' || $this->authToken === '') {
            $this->logger->warning('TwilioSmsProvider: credentials not configured, skipping SMS');

            return false;
        }

        try {
            $response = $this->httpClient->request('POST', sprintf(
                'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json',
                $this->accountSid,
            ), [
                'auth_basic' => [$this->accountSid, $this->authToken],
                'body' => [
                    'From' => $this->fromNumber,
                    'To' => $phoneNumber,
                    'Body' => $message,
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                $this->logger->info('SMS sent to {phone} via Twilio', ['phone' => $phoneNumber]);

                return true;
            }

            $this->logger->error('Twilio SMS failed with status {status}', [
                'status' => $statusCode,
                'phone' => $phoneNumber,
                'response' => $response->getContent(false),
            ]);

            return false;
        } catch (\Throwable $e) {
            $this->logger->error('Twilio SMS exception: {error}', [
                'error' => $e->getMessage(),
                'phone' => $phoneNumber,
            ]);

            return false;
        }
    }
}
