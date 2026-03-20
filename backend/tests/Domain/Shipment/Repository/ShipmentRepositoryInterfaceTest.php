<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shipment\Repository;

use App\Domain\Shipment\Repository\ShipmentRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Verifies ShipmentRepositoryInterface contract exists and defines expected methods.
 */
final class ShipmentRepositoryInterfaceTest extends TestCase
{
    #[Test]
    public function interfaceExists(): void
    {
        self::assertTrue(
            interface_exists(ShipmentRepositoryInterface::class),
            'ShipmentRepositoryInterface must exist in Domain layer',
        );
    }

    #[Test]
    public function interfaceDefinesFindOneByPublicId(): void
    {
        $ref = new \ReflectionClass(ShipmentRepositoryInterface::class);
        self::assertTrue($ref->hasMethod('findOneByPublicId'));

        $method = $ref->getMethod('findOneByPublicId');
        self::assertCount(1, $method->getParameters());
        self::assertSame('string', (string) $method->getParameters()[0]->getType());
    }

    #[Test]
    public function interfaceDefinesFindOneByTrackingToken(): void
    {
        $ref = new \ReflectionClass(ShipmentRepositoryInterface::class);
        self::assertTrue($ref->hasMethod('findOneByTrackingToken'));
    }

    #[Test]
    public function interfaceDefinesSave(): void
    {
        $ref = new \ReflectionClass(ShipmentRepositoryInterface::class);
        self::assertTrue($ref->hasMethod('save'));
    }

    #[Test]
    public function interfaceDefinesRemove(): void
    {
        $ref = new \ReflectionClass(ShipmentRepositoryInterface::class);
        self::assertTrue($ref->hasMethod('remove'));
    }

    #[Test]
    public function interfaceDefinesFlush(): void
    {
        $ref = new \ReflectionClass(ShipmentRepositoryInterface::class);
        self::assertTrue($ref->hasMethod('flush'));
    }
}
