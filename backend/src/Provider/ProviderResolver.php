<?php

declare(strict_types=1);

namespace App\Provider;

use App\Entity\Customer;
use App\Repository\CustomerIntegrationRepository;

final class ProviderResolver implements ProviderResolverInterface
{
    public function __construct(
        private readonly CustomerIntegrationRepository $integrationRepository,
        private readonly ProviderFactoryRegistry $factoryRegistry,
    ) {}

    public function resolve(ServiceType $service, ?Customer $customer): object
    {
        if ($customer === null) {
            return $this->factoryRegistry->createDefault($service);
        }

        $integrations = $this->integrationRepository->findActiveByCustomerAndService($customer, $service);

        if ($integrations === []) {
            return $this->factoryRegistry->createDefault($service);
        }

        return $this->factoryRegistry->create($integrations[0]);
    }

    public function resolveWithFallback(ServiceType $service, ?Customer $customer): FallbackChain
    {
        if ($customer === null) {
            return new FallbackChain([$this->factoryRegistry->createDefault($service)]);
        }

        $integrations = $this->integrationRepository->findActiveByCustomerAndService($customer, $service);

        if ($integrations === []) {
            return new FallbackChain([$this->factoryRegistry->createDefault($service)]);
        }

        $providers = array_map(
            fn ($integration) => $this->factoryRegistry->create($integration),
            $integrations,
        );

        return new FallbackChain($providers);
    }
}
