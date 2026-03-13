<?php

declare(strict_types=1);

namespace App\Provider\RouteOptimizer;

use App\Provider\ProviderFactoryInterface;
use App\Provider\ServiceType;
use App\RouteOptimization\VroomRouteOptimizer;
use App\Service\OptimizationLogger;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class VroomFactory implements ProviderFactoryInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly OptimizationLogger $optimizationLogger,
        private readonly string $defaultVroomUrl,
    ) {
    }

    public function create(array $config): VroomRouteOptimizer
    {
        $url = $config['url'] ?? $this->defaultVroomUrl;

        return new VroomRouteOptimizer($this->httpClient, $this->logger, $this->optimizationLogger, $url);
    }

    public function getProviderType(): string
    {
        return RouteOptimizerProvider::Vroom->value;
    }

    public function getServiceType(): ServiceType
    {
        return ServiceType::RouteOptimizer;
    }
}
