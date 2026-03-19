<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tracking;

use App\Tracking\DeviceInfo;
use App\Tracking\GpsDeviceManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Contract test for GpsDeviceManagerInterface.
 *
 * Concrete implementations must extend this class and provide
 * an instance via createManager().
 */
abstract class GpsDeviceManagerContractTest extends TestCase
{
    abstract protected function createManager(): GpsDeviceManagerInterface;

    #[Test]
    public function implementsGpsDeviceManagerInterface(): void
    {
        $manager = $this->createManager();

        self::assertInstanceOf(GpsDeviceManagerInterface::class, $manager);
    }

    #[Test]
    public function getDevicesReturnsArray(): void
    {
        $manager = $this->createManager();

        $result = $manager->getDevices();

        self::assertIsArray($result);
    }

    #[Test]
    public function createDeviceReturnsDeviceInfo(): void
    {
        $manager = $this->createManager();

        $result = $manager->createDevice('Test Device', 'test-unique-id');

        self::assertInstanceOf(DeviceInfo::class, $result);
    }

    #[Test]
    public function getSessionCookieReturnsNullableString(): void
    {
        $manager = $this->createManager();

        $result = $manager->getSessionCookie();

        self::assertTrue($result === null || \is_string($result));
    }
}
