<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider\Realtime;

use App\Provider\Realtime\MercureFactory;
use App\Provider\Realtime\RealtimeProviderType;
use App\Provider\ServiceType;
use App\Realtime\MercurePublisher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mercure\HubInterface;

#[CoversClass(MercureFactory::class)]
final class MercureFactoryTest extends TestCase
{
    #[Test]
    public function createReturnsMercurePublisher(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $factory = new MercureFactory($hub, new NullLogger());

        $result = $factory->create([]);

        self::assertInstanceOf(MercurePublisher::class, $result);
    }

    #[Test]
    public function getProviderTypeReturnsMercure(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $factory = new MercureFactory($hub, new NullLogger());

        self::assertSame(RealtimeProviderType::Mercure->value, $factory->getProviderType());
    }

    #[Test]
    public function getServiceTypeReturnsRealtimePublisher(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $factory = new MercureFactory($hub, new NullLogger());

        self::assertSame(ServiceType::RealtimePublisher, $factory->getServiceType());
    }
}
