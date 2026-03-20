<?php

declare(strict_types=1);

namespace App\Doctrine\Types;

use App\Infrastructure\Security\CredentialEncryptor;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 4096)]
final class EncryptedJsonTypeInitializer
{
    public function __construct(private readonly CredentialEncryptor $encryptor)
    {
        EncryptedJsonType::setEncryptor($this->encryptor);
    }

    public function __invoke(): void
    {
        EncryptedJsonType::setEncryptor($this->encryptor);
    }
}
