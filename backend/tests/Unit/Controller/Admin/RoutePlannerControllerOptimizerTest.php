<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Admin;

use App\Controller\Admin\RoutePlannerController;
use App\Provider\ProviderFactoryInterface;
use App\Provider\ProviderFactoryRegistry;
use App\Provider\ServiceType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RoutePlannerController::class)]
final class RoutePlannerControllerOptimizerTest extends TestCase
{
    #[Test]
    public function optimizers_returns_available_route_optimizers(): void
    {
        $vroomFactory = $this->createFactory('vroom', ServiceType::RouteOptimizer);
        $greedyFactory = $this->createFactory('greedy', ServiceType::RouteOptimizer);
        $osrmFactory = $this->createFactory('osrm', ServiceType::RoutingEngine);

        $registry = new ProviderFactoryRegistry(
            [$vroomFactory, $greedyFactory, $osrmFactory],
            [],
        );

        $controller = $this->getMockBuilder(RoutePlannerController::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $response = $controller->optimizers($registry);

        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertIsArray($data);
        self::assertCount(2, $data); // Only route_optimizer, not routing_engine

        $names = array_column($data, 'name');
        self::assertContains('vroom', $names);
        self::assertContains('greedy', $names);
    }

    private function createFactory(string $providerType, ServiceType $serviceType): ProviderFactoryInterface
    {
        return new class ($providerType, $serviceType) implements ProviderFactoryInterface {
            public function __construct(
                private readonly string $pt,
                private readonly ServiceType $st,
            ) {}

            public function create(array $config): object
            {
                return new \stdClass();
            }

            public function getProviderType(): string
            {
                return $this->pt;
            }

            public function getServiceType(): ServiceType
            {
                return $this->st;
            }
        };
    }
}
