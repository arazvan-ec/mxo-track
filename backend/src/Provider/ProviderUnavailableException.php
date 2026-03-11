<?php

declare(strict_types=1);

namespace App\Provider;

final class ProviderUnavailableException extends \RuntimeException
{
    public function __construct(
        public readonly string $providerType,
        string $message = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : "Provider '{$providerType}' is unavailable", 0, $previous);
    }
}
