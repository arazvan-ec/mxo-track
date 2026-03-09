<?php

declare(strict_types=1);

namespace App\Notification\Provider;

interface WhatsAppProviderInterface
{
    /**
     * @param array<string, string> $parameters
     */
    public function sendTemplate(string $phoneNumber, string $templateName, array $parameters): bool;

    public function sendText(string $phoneNumber, string $message): bool;
}
