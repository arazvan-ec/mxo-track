<?php

declare(strict_types=1);

namespace App\Notification\Provider;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class TwilioWhatsAppProvider implements WhatsAppProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $accountSid,
        private readonly string $authToken,
        private readonly string $fromNumber,
    ) {
    }

    public function sendTemplate(string $phoneNumber, string $templateName, array $parameters): bool
    {
        $body = sprintf(
            "Template: %s\nParams: %s",
            $templateName,
            json_encode($parameters, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        );

        return $this->doSend($phoneNumber, $body);
    }

    public function sendText(string $phoneNumber, string $message): bool
    {
        return $this->doSend($phoneNumber, $message);
    }

    private function doSend(string $phoneNumber, string $body): bool
    {
        if ($this->accountSid === '' || $this->authToken === '') {
            $this->logger->warning('TwilioWhatsAppProvider: credentials not configured, skipping');

            return false;
        }

        try {
            $response = $this->httpClient->request('POST', sprintf(
                'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json',
                $this->accountSid,
            ), [
                'auth_basic' => [$this->accountSid, $this->authToken],
                'body' => [
                    'From' => 'whatsapp:' . $this->fromNumber,
                    'To' => 'whatsapp:' . $phoneNumber,
                    'Body' => $body,
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                $this->logger->info('WhatsApp sent to {phone} via Twilio', ['phone' => $phoneNumber]);

                return true;
            }

            $this->logger->error('Twilio WhatsApp failed with status {status}', [
                'status' => $statusCode,
                'phone' => $phoneNumber,
                'response' => $response->getContent(false),
            ]);

            return false;
        } catch (\Throwable $e) {
            $this->logger->error('Twilio WhatsApp exception: {error}', [
                'error' => $e->getMessage(),
                'phone' => $phoneNumber,
            ]);

            return false;
        }
    }
}
