<?php

declare(strict_types=1);

namespace App\Doctrine\Types;

use App\Infrastructure\Security\CredentialEncryptor;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class EncryptedJsonType extends Type
{
    public const string NAME = 'encrypted_json';

    private static ?CredentialEncryptor $encryptor = null;

    public static function setEncryptor(CredentialEncryptor $encryptor): void
    {
        self::$encryptor = $encryptor;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'TEXT';
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        return self::getEncryptor()->encrypt($value);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?array
    {
        if ($value === null) {
            return null;
        }

        return self::getEncryptor()->decrypt($value);
    }

    private static function getEncryptor(): CredentialEncryptor
    {
        if (self::$encryptor === null) {
            throw new \LogicException('CredentialEncryptor has not been injected into EncryptedJsonType.');
        }

        return self::$encryptor;
    }
}
