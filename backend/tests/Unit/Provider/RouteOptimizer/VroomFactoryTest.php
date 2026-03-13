<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider\RouteOptimizer;

use App\Provider\RouteOptimizer\RouteOptimizerProvider;
use App\Provider\RouteOptimizer\VroomFactory;
use App\Provider\ServiceType;
use App\RouteOptimization\VroomRouteOptimizer;
use App\Service\OptimizationLogger;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;

#[CoversClass(VroomFactory::class)]
final class VroomFactoryTest extends TestCase
{
    private function createFactory(string $url = 'http://default-vroom:3000'): VroomFactory
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $logger = new OptimizationLogger($em);

        return new VroomFactory(
            new MockHttpClient(),
            new NullLogger(),
            $logger,
            $url,
        );
    }

    #[Test]
    public function createReturnsVroomRouteOptimizerWithCustomUrl(): void
    {
        $factory = $this->createFactory();

        $result = $factory->create(['url' => 'http://custom-vroom:5100']);

        self::assertInstanceOf(VroomRouteOptimizer::class, $result);
    }

    #[Test]
    public function createUsesDefaultUrlWhenConfigEmpty(): void
    {
        $factory = $this->createFactory();

        $result = $factory->create([]);

        self::assertInstanceOf(VroomRouteOptimizer::class, $result);
    }

    #[Test]
    public function getProviderTypeReturnsVroom(): void
    {
        $factory = $this->createFactory();

        self::assertSame(RouteOptimizerProvider::Vroom->value, $factory->getProviderType());
    }

    #[Test]
    public function getServiceTypeReturnsRouteOptimizer(): void
    {
        $factory = $this->createFactory();

        self::assertSame(ServiceType::RouteOptimizer, $factory->getServiceType());
    }
}
