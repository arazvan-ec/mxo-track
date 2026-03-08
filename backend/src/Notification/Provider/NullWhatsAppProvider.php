<?php

declare(strict_types=1);

namespace App\Notification\Provider;

use Psr\Log\LoggerInterface;

final class NullWhatsAppProvider implements WhatsAppProviderInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function sendTemplate(string $phoneNumber, string $templateName, array $parameters): bool
    {
        $this->logger->debug('NullWhatsAppProvider: template {template} to {phone} suppressed', [
            'phone' => $phoneNumber,
            'template' => $templateName,
            'parameters' => $parameters,
        ]);

        return true;
    }

    public function sendText(string $phoneNumber, string $message): bool
    {
        $this->logger->debug('NullWhatsAppProvider: text to {phone} suppressed', [
            'phone' => $phoneNumber,
            'message' => $message,
        ]);

        return true;
    }
}
