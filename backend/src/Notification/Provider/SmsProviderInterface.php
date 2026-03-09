<?php

declare(strict_types=1);

namespace App\Notification\Provider;

interface SmsProviderInterface
{
    public function sendSms(string $phoneNumber, string $message): bool;
}
