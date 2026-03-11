<?php

declare(strict_types=1);

namespace App\Provider\Routing;

use App\Provider\ProviderFactoryInterface;
use App\Provider\ServiceType;
use App\Routing\OsrmRoutingEngine;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OsrmFactory implements ProviderFactoryInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $defaultOsrmUrl,
    ) {
    }

    public function create(array $config): OsrmRoutingEngine
    {
        $url = $config['url'] ?? $this->defaultOsrmUrl;

        return new OsrmRoutingEngine($this->httpClient, $this->logger, $url);
    }

    public function getProviderType(): string
    {
        return RoutingProvider::Osrm->value;
    }

    public function getServiceType(): ServiceType
    {
        return ServiceType::RoutingEngine;
    }
}
