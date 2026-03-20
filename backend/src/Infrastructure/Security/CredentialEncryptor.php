<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CredentialEncryptor
{
    private string $key;

    public function __construct(
        #[Autowire('%env(APP_SECRET)%')] string $appSecret,
    ) {
        $this->key = sodium_crypto_generichash($appSecret, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public function encrypt(array $data): string
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($json, $nonce, $this->key);

        return base64_encode($nonce . $ciphertext);
    }

    public function decrypt(string $encrypted): array
    {
        $decoded = base64_decode($encrypted, true);
        if ($decoded === false || \strlen($decoded) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Failed to decode encrypted credentials: invalid data.');
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $json = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);

        if ($json === false) {
            throw new \RuntimeException('Failed to decrypt credentials: authentication failed (wrong key or tampered data).');
        }

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }
}
