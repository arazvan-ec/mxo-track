<?php

declare(strict_types=1);

namespace App\Provider;

use App\Entity\Customer;

final class CachedProviderResolver implements ProviderResolverInterface
{
    /** @var array<string, object> */
    private array $cache = [];

    public function __construct(private readonly ProviderResolverInterface $inner) {}

    public function resolve(ServiceType $service, ?Customer $customer): object
    {
        $key = $this->cacheKey($service, $customer);

        return $this->cache[$key] ??= $this->inner->resolve($service, $customer);
    }

    public function resolveWithFallback(ServiceType $service, ?Customer $customer): FallbackChain
    {
        return $this->inner->resolveWithFallback($service, $customer);
    }

    private function cacheKey(ServiceType $service, ?Customer $customer): string
    {
        $customerId = $customer?->getId() ?? 'default';

        return "{$service->value}:{$customerId}";
    }
}
