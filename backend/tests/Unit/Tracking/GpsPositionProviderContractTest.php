<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tracking;

use App\Tracking\GpsPositionProviderInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Contract test for GpsPositionProviderInterface.
 *
 * Concrete implementations must extend this class and provide
 * an instance via createProvider().
 */
abstract class GpsPositionProviderContractTest extends TestCase
{
    abstract protected function createProvider(): GpsPositionProviderInterface;

    #[Test]
    public function implementsGpsPositionProviderInterface(): void
    {
        $provider = $this->createProvider();

        self::assertInstanceOf(GpsPositionProviderInterface::class, $provider);
    }

    #[Test]
    public function isAvailableReturnsBool(): void
    {
        $provider = $this->createProvider();

        $result = $provider->isAvailable();

        self::assertIsBool($result);
    }

    #[Test]
    public function getPositionsReturnsArray(): void
    {
        $provider = $this->createProvider();

        $result = $provider->getPositions(1);

        self::assertIsArray($result);
    }

    #[Test]
    public function getPositionsWithSinceReturnsArray(): void
    {
        $provider = $this->createProvider();

        $result = $provider->getPositions(1, new \DateTimeImmutable());

        self::assertIsArray($result);
    }
}
