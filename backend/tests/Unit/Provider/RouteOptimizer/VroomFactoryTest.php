<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider\RouteOptimizer;

use App\Provider\RouteOptimizer\RouteOptimizerProvider;
use App\Provider\RouteOptimizer\VroomFactory;
use App\Provider\ServiceType;
use App\RouteOptimization\VroomRouteOptimizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;

#[CoversClass(VroomFactory::class)]
final class VroomFactoryTest extends TestCase
{
    #[Test]
    public function createReturnsVroomRouteOptimizerWithCustomUrl(): void
    {
        $factory = new VroomFactory(
            new MockHttpClient(),
            new NullLogger(),
            'http://default-vroom:3000',
        );

        $result = $factory->create(['url' => 'http://custom-vroom:5100']);

        self::assertInstanceOf(VroomRouteOptimizer::class, $result);
    }

    #[Test]
    public function createUsesDefaultUrlWhenConfigEmpty(): void
    {
        $factory = new VroomFactory(
            new MockHttpClient(),
            new NullLogger(),
            'http://default-vroom:3000',
        );

        $result = $factory->create([]);

        self::assertInstanceOf(VroomRouteOptimizer::class, $result);
    }

    #[Test]
    public function getProviderTypeReturnsVroom(): void
    {
        $factory = new VroomFactory(
            new MockHttpClient(),
            new NullLogger(),
            'http://default-vroom:3000',
        );

        self::assertSame(RouteOptimizerProvider::Vroom->value, $factory->getProviderType());
    }

    #[Test]
    public function getServiceTypeReturnsRouteOptimizer(): void
    {
        $factory = new VroomFactory(
            new MockHttpClient(),
            new NullLogger(),
            'http://default-vroom:3000',
        );

        self::assertSame(ServiceType::RouteOptimizer, $factory->getServiceType());
    }
}
