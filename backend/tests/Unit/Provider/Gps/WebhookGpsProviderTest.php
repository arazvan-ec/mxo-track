<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider\Gps;

use App\Provider\Gps\WebhookGpsProvider;
use App\Tests\Unit\Tracking\GpsPositionProviderContractTest;
use App\Tracking\GpsPositionProviderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(WebhookGpsProvider::class)]
final class WebhookGpsProviderTest extends GpsPositionProviderContractTest
{
    private WebhookGpsProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new WebhookGpsProvider();
    }

    protected function createProvider(): GpsPositionProviderInterface
    {
        return $this->provider;
    }

    #[Test]
    public function isAvailableReturnsTrue(): void
    {
        self::assertTrue($this->provider->isAvailable());
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
