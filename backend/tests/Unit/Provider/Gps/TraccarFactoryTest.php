<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider\Gps;

use App\Provider\Gps\GpsProviderType;
use App\Provider\Gps\TraccarFactory;
use App\Provider\ServiceType;
use App\Tracking\TraccarGpsProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;

#[CoversClass(TraccarFactory::class)]
final class TraccarFactoryTest extends TestCase
{
    #[Test]
    public function createReturnsTraccarGpsProviderWithCustomConfig(): void
    {
        $factory = new TraccarFactory(
            new MockHttpClient(),
            new NullLogger(),
            'http://default-traccar:8082',
            'default-user',
            'default-pass',
        );

        $result = $factory->create([
            'base_url' => 'http://custom-traccar:8082',
            'username' => 'custom-user',
            'password' => 'custom-pass',
        ]);

        self::assertInstanceOf(TraccarGpsProvider::class, $result);
    }

    #[Test]
    public function createUsesDefaultsWhenConfigEmpty(): void
    {
        $factory = new TraccarFactory(
            new MockHttpClient(),
            new NullLogger(),
            'http://default-traccar:8082',
            'default-user',
            'default-pass',
        );

        $result = $factory->create([]);

        self::assertInstanceOf(TraccarGpsProvider::class, $result);
    }

    #[Test]
    public function getProviderTypeReturnsTraccar(): void
    {
        $factory = new TraccarFactory(
            new MockHttpClient(),
            new NullLogger(),
            'http://default-traccar:8082',
            'default-user',
            'default-pass',
        );

        self::assertSame(GpsProviderType::Traccar->value, $factory->getProviderType());
    }

    #[Test]
    public function getServiceTypeReturnsGpsProvider(): void
    {
        $factory = new TraccarFactory(
            new MockHttpClient(),
            new NullLogger(),
            'http://default-traccar:8082',
            'default-user',
            'default-pass',
        );

        self::assertSame(ServiceType::GpsProvider, $factory->getServiceType());
    }
}
