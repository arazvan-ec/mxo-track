<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Security;

use App\Infrastructure\Security\CredentialEncryptor;
use PHPUnit\Framework\TestCase;

class CredentialEncryptorTest extends TestCase
{
    private CredentialEncryptor $encryptor;

    protected function setUp(): void
    {
        $this->encryptor = new CredentialEncryptor('test-app-secret-key-1234');
    }

    public function testEncryptAndDecryptRoundTrip(): void
    {
        $data = ['api_key' => 'sk-123', 'url' => 'https://api.example.com'];

        $encrypted = $this->encryptor->encrypt($data);
        $decrypted = $this->encryptor->decrypt($encrypted);

        self::assertSame($data, $decrypted);
    }

    public function testEncryptedOutputIsNotPlaintext(): void
    {
        $data = ['api_key' => 'sk-123'];

        $encrypted = $this->encryptor->encrypt($data);

        self::assertStringNotContainsString('sk-123', $encrypted);
        self::assertStringNotContainsString('api_key', $encrypted);
    }

    public function testDifferentKeysCannotDecrypt(): void
    {
        $data = ['secret' => 'value'];
        $encrypted = $this->encryptor->encrypt($data);

        $otherEncryptor = new CredentialEncryptor('different-secret-key-5678');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('authentication failed');
        $otherEncryptor->decrypt($encrypted);
    }

    public function testEmptyArrayEncryptsAndDecrypts(): void
    {
        $encrypted = $this->encryptor->encrypt([]);
        $decrypted = $this->encryptor->decrypt($encrypted);

        self::assertSame([], $decrypted);
    }

    public function testEncryptedOutputIsDifferentEachTime(): void
    {
        $data = ['key' => 'value'];

        $encrypted1 = $this->encryptor->encrypt($data);
        $encrypted2 = $this->encryptor->encrypt($data);

        self::assertNotSame($encrypted1, $encrypted2);
    }
}
