<?php

declare(strict_types=1);

namespace App\Provider\Routing;

use App\Provider\ProviderResolverInterface;
use App\Provider\ServiceType;
use App\Provider\TenantContext;
use App\Routing\MultiWaypointRouteResult;
use App\Routing\RouteResult;
use App\Routing\RoutingEngineInterface;

final class TenantAwareRoutingEngine implements RoutingEngineInterface
{
    public function __construct(
        private readonly ProviderResolverInterface $resolver,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function route(float $fromLat, float $fromLng, float $toLat, float $toLng): RouteResult
    {
        $customer = $this->tenantContext->getCustomer();
        /** @var RoutingEngineInterface $engine */
        $engine = $this->resolver->resolve(ServiceType::RoutingEngine, $customer);

        return $engine->route($fromLat, $fromLng, $toLat, $toLng);
    }

    public function routeWithWaypoints(array $waypoints): MultiWaypointRouteResult
    {
        $customer = $this->tenantContext->getCustomer();
        /** @var RoutingEngineInterface $engine */
        $engine = $this->resolver->resolve(ServiceType::RoutingEngine, $customer);

        return $engine->routeWithWaypoints($waypoints);
    }
}
