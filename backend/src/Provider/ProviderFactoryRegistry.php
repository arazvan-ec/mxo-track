<?php

declare(strict_types=1);

namespace App\Provider;

use App\Entity\CustomerIntegration;

class ProviderFactoryRegistry
{
    /** @var array<string, ProviderFactoryInterface> */
    private array $factories = [];

    /**
     * @param iterable<ProviderFactoryInterface> $factories
     * @param array<string, string>              $defaults service_type_value => provider_type
     */
    public function __construct(
        iterable $factories,
        private readonly array $defaults,
    ) {
        foreach ($factories as $factory) {
            $this->factories[$factory->getProviderType()] = $factory;
        }
    }

    public function create(CustomerIntegration $integration): object
    {
        $factory = $this->factories[$integration->getProviderType()]
            ?? throw new \InvalidArgumentException("Unknown provider: {$integration->getProviderType()}");

        return $factory->create($integration->getConfig());
    }

    public function createDefault(ServiceType $service): object
    {
        $providerType = $this->defaults[$service->value]
            ?? throw new \RuntimeException("No default provider configured for {$service->value}");

        $factory = $this->factories[$providerType]
            ?? throw new \RuntimeException("Default provider factory not found: {$providerType}");

        return $factory->create([]);
    }

    /**
     * Create a provider instance by its provider type name using default config.
     */
    public function createByName(string $providerType): object
    {
        $factory = $this->factories[$providerType]
            ?? throw new \InvalidArgumentException("Unknown provider: {$providerType}");

        return $factory->create([]);
    }

    /**
     * @return array<string, list<string>> Keyed by service type value
     */
    public function getAvailableProviders(): array
    {
        $grouped = [];
        foreach ($this->factories as $factory) {
            $grouped[$factory->getServiceType()->value][] = $factory->getProviderType();
        }

        return $grouped;
    }
}
