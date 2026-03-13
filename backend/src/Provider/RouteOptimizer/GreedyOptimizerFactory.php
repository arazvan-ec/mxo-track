<?php

declare(strict_types=1);

namespace App\Provider\RouteOptimizer;

use App\Provider\ProviderFactoryInterface;
use App\Provider\ServiceType;
use App\Service\OptimizationLogger;

final class GreedyOptimizerFactory implements ProviderFactoryInterface
{
    public function __construct(
        private readonly OptimizationLogger $logger,
    ) {
    }

    public function create(array $config): GreedyOptimizer
    {
        return new GreedyOptimizer($this->logger);
    }

    public function getProviderType(): string
    {
        return RouteOptimizerProvider::Greedy->value;
    }

    public function getServiceType(): ServiceType
    {
        return ServiceType::RouteOptimizer;
    }
}
