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
        private readonly string $defaultApiKey = '',
    ) {
    }

    public function create(array $config): GoogleDirectionsEngine
    {
        if (!isset($config['api_key']) && $this->defaultApiKey !== '') {
            $config['api_key'] = $this->defaultApiKey;
        }

        if (!isset($config['api_key']) || $config['api_key'] === '') {
            throw new \InvalidArgumentException(
                'Google Directions API key is not configured. '
                . 'Set the GOOGLE_DIRECTIONS_API_KEY environment variable or configure an API key for the customer integration.'
            );
        }

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
