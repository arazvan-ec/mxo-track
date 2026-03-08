<?php

declare(strict_types=1);

namespace App\Routing;

use Psr\Log\LoggerInterface;

/**
 * Null adapter for the RoutingEngine port.
 * Returns zero distances and durations. Useful for testing.
 */
final class NullRoutingEngine implements RoutingEngineInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function route(float $fromLat, float $fromLng, float $toLat, float $toLng): RouteResult
    {
        $this->logger->debug('NullRoutingEngine::route called.', [
            'from' => [$fromLat, $fromLng],
            'to' => [$toLat, $toLng],
        ]);

        return new RouteResult(0.0, 0.0);
    }

    public function routeWithWaypoints(array $waypoints): MultiWaypointRouteResult
    {
        $this->logger->debug('NullRoutingEngine::routeWithWaypoints called.', [
            'waypointCount' => \count($waypoints),
        ]);

        return new MultiWaypointRouteResult(0.0, 0.0, []);
    }
}
