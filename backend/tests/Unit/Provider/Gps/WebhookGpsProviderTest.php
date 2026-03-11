<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider\Gps;

use App\Provider\Gps\WebhookGpsProvider;
use App\Tracking\DeviceInfo;
use App\Tracking\GpsDeviceProviderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebhookGpsProvider::class)]
final class WebhookGpsProviderTest extends TestCase
{
    private WebhookGpsProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new WebhookGpsProvider();
    }

    #[Test]
    public function implementsGpsDeviceProviderInterface(): void
    {
        self::assertInstanceOf(GpsDeviceProviderInterface::class, $this->provider);
    }

    #[Test]
    public function isAvailableReturnsTrue(): void
    {
        self::assertTrue($this->provider->isAvailable());
    }

    #[Test]
    public function loginIsNoOp(): void
    {
        $this->provider->login();

        // No exception thrown — login is a no-op for webhook provider
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function getSessionCookieReturnsNull(): void
    {
        self::assertNull($this->provider->getSessionCookie());
    }

    #[Test]
    public function getDevicesReturnsEmptyArray(): void
    {
        self::assertSame([], $this->provider->getDevices());
    }

    #[Test]
    public function createDeviceReturnsDeviceInfoWithProvidedData(): void
    {
        $result = $this->provider->createDevice('Test Device', 'unique-123');

        self::assertInstanceOf(DeviceInfo::class, $result);
        self::assertSame(0, $result->id);
        self::assertSame('Test Device', $result->name);
        self::assertSame('unique-123', $result->uniqueId);
    }

    #[Test]
    public function getPositionsReturnsEmptyArray(): void
    {
        self::assertSame([], $this->provider->getPositions(42));
    }

    #[Test]
    public function getPositionsWithSinceReturnsEmptyArray(): void
    {
        self::assertSame([], $this->provider->getPositions(42, new \DateTimeImmutable()));
    }
}
