<?php

declare(strict_types=1);

namespace App\Provider;

enum ServiceType: string
{
    case RouteOptimizer = 'route_optimizer';
    case RoutingEngine = 'routing_engine';
    case GpsProvider = 'gps_provider';
    case RealtimePublisher = 'realtime_publisher';
}
