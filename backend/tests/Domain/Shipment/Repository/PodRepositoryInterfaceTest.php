<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shipment\Repository;

use App\Domain\Shipment\Repository\PodRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Verifies PodRepositoryInterface contract exists and defines expected methods.
 */
final class PodRepositoryInterfaceTest extends TestCase
{
    #[Test]
    public function interfaceExists(): void
    {
        self::assertTrue(
            interface_exists(PodRepositoryInterface::class),
            'PodRepositoryInterface must exist in Domain layer',
        );
    }

    #[Test]
    public function interfaceDefinesFindOneByPublicId(): void
    {
        $ref = new \ReflectionClass(PodRepositoryInterface::class);
        self::assertTrue($ref->hasMethod('findOneByPublicId'));
    }

    #[Test]
    public function interfaceDefinesSave(): void
    {
        $ref = new \ReflectionClass(PodRepositoryInterface::class);
        self::assertTrue($ref->hasMethod('save'));
    }

    #[Test]
    public function interfaceDefinesFlush(): void
    {
        $ref = new \ReflectionClass(PodRepositoryInterface::class);
        self::assertTrue($ref->hasMethod('flush'));
    }
}
