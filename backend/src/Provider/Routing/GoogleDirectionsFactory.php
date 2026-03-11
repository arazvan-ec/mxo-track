<?php

declare(strict_types=1);

namespace App\Provider\Routing;

use App\Provider\ProviderFactoryInterface;
use App\Provider\ServiceType;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GoogleDirectionsFactory implements ProviderFactoryInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function create(array $config): GoogleDirectionsEngine
    {
        return new GoogleDirectionsEngine(
            $this->httpClient,
            GoogleDirectionsConfig::fromArray($config),
        );
    }

    public function getProviderType(): string
    {
        return RoutingProvider::GoogleDirections->value;
    }

    public function getServiceType(): ServiceType
    {
        return ServiceType::RoutingEngine;
    }
}
