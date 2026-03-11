<?php

declare(strict_types=1);

namespace App\Provider\RouteOptimizer;

use App\Provider\ProviderResolverInterface;
use App\Provider\ServiceType;
use App\Provider\TenantContext;
use App\RouteOptimization\OptimizationResult;
use App\RouteOptimization\RouteOptimizerInterface;

final class TenantAwareRouteOptimizer implements RouteOptimizerInterface
{
    public function __construct(
        private readonly ProviderResolverInterface $resolver,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function optimize(array $vehicles, array $jobs): OptimizationResult
    {
        $customer = $this->tenantContext->getCustomer();
        /** @var RouteOptimizerInterface $optimizer */
        $optimizer = $this->resolver->resolve(ServiceType::RouteOptimizer, $customer);

        return $optimizer->optimize($vehicles, $jobs);
    }
}
