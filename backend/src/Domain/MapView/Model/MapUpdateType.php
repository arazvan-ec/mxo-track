<?php

declare(strict_types=1);

namespace App\Domain\MapView\Model;

enum MapUpdateType: string
{
    case StopDelivered = 'stop_delivered';
    case StopException = 'stop_exception';
    case RouteStarted = 'route_started';
    case RouteCompleted = 'route_completed';
    case RouteCancelled = 'route_cancelled';
    case RouteOptimized = 'route_optimized';
    case RouteAssigned = 'route_assigned';
    case RoutesBuilt = 'routes_built';
    case EtaChanged = 'eta_changed';
    case DeviationDetected = 'deviation_detected';
    case DeviationEnded = 'deviation_ended';
    case RouteSnapshot = 'route_snapshot';
}
