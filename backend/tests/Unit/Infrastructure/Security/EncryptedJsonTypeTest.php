<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Security;

use App\Doctrine\Types\EncryptedJsonType;
use App\Infrastructure\Security\CredentialEncryptor;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\TestCase;

class EncryptedJsonTypeTest extends TestCase
{
    private EncryptedJsonType $type;
    private PostgreSQLPlatform $platform;

    protected function setUp(): void
    {
        EncryptedJsonType::setEncryptor(new CredentialEncryptor('test-secret'));
        $this->type = new EncryptedJsonType();
        $this->platform = new PostgreSQLPlatform();
    }

    public function testSqlDeclarationIsText(): void
    {
        self::assertSame('TEXT', $this->type->getSQLDeclaration([], $this->platform));
    }

    public function testConvertToDatabaseValueEncryptsArray(): void
    {
        $result = $this->type->convertToDatabaseValue(['key' => 'val'], $this->platform);

        self::assertIsString($result);
        self::assertStringNotContainsString('key', $result);
    }

    public function testConvertToPhpValueDecryptsToArray(): void
    {
        $encrypted = $this->type->convertToDatabaseValue(['api_key' => 'secret'], $this->platform);
        $decrypted = $this->type->convertToPHPValue($encrypted, $this->platform);

        self::assertSame(['api_key' => 'secret'], $decrypted);
    }

    public function testNullDatabaseValueReturnsNull(): void
    {
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    public function testNullPhpValueReturnsNull(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }
}
