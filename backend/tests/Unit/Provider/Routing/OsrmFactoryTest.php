<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider\Routing;

use App\Provider\Routing\OsrmFactory;
use App\Provider\Routing\RoutingProvider;
use App\Provider\ServiceType;
use App\Routing\OsrmRoutingEngine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;

#[CoversClass(OsrmFactory::class)]
final class OsrmFactoryTest extends TestCase
{
    #[Test]
    public function createReturnsOsrmRoutingEngineWithCustomUrl(): void
    {
        $factory = new OsrmFactory(
            new MockHttpClient(),
            new NullLogger(),
            'http://default-osrm:5000',
        );

        $result = $factory->create(['url' => 'http://custom-osrm:5000']);

        self::assertInstanceOf(OsrmRoutingEngine::class, $result);
    }

    #[Test]
    public function createUsesDefaultUrlWhenConfigEmpty(): void
    {
        $factory = new OsrmFactory(
            new MockHttpClient(),
            new NullLogger(),
            'http://default-osrm:5000',
        );

        $result = $factory->create([]);

        self::assertInstanceOf(OsrmRoutingEngine::class, $result);
    }

    #[Test]
    public function getProviderTypeReturnsOsrm(): void
    {
        $factory = new OsrmFactory(
            new MockHttpClient(),
            new NullLogger(),
            'http://default-osrm:5000',
        );

        self::assertSame(RoutingProvider::Osrm->value, $factory->getProviderType());
    }

    #[Test]
    public function getServiceTypeReturnsRoutingEngine(): void
    {
        $factory = new OsrmFactory(
            new MockHttpClient(),
            new NullLogger(),
            'http://default-osrm:5000',
        );

        self::assertSame(ServiceType::RoutingEngine, $factory->getServiceType());
    }
}
