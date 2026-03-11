<?php

declare(strict_types=1);

namespace App\Provider;

use App\Entity\Customer;

interface ProviderResolverInterface
{
    /**
     * Resolve the active provider for a customer and service.
     * If $customer is null, returns the global default.
     */
    public function resolve(ServiceType $service, ?Customer $customer): object;

    /**
     * Resolve with fallback chain support.
     */
    public function resolveWithFallback(ServiceType $service, ?Customer $customer): FallbackChain;
}
