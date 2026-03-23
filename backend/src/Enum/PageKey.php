<?php

declare(strict_types=1);

namespace App\Enum;

enum PageKey: string
{
    case FLEET_MAP = 'fleet_map';
    case TEST_ROUTING = 'test_routing';
    case ROUTE_PLANNER = 'route_planner';
    case ROUTE_ANALYSIS = 'route_analysis';
    case ROUTE_DETAIL = 'route_detail';
    case SHIPMENT_TRACKING = 'shipment_tracking';
    case DRIVER_ROUTE = 'driver_route';
    case CUSTOMER_TRACKING = 'customer_tracking';
}
