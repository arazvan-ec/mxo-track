<?php

declare(strict_types=1);

namespace App\Enum;

enum WidgetType: string
{
    case METRIC_PAIRS = 'metric_pairs';
    case ROUTE_CARD_LIST = 'route_card_list';
    case STOP_LIST = 'stop_list';
    case VEHICLE_INFO = 'vehicle_info';
    case DRIVER_INFO = 'driver_info';
    case SHIPMENT_DETAILS = 'shipment_details';
    case DELIVERY_TIMELINE = 'delivery_timeline';
    case KPI_PILLS = 'kpi_pills';
    case MAP_LEGEND = 'map_legend';
    case ROUTE_COMPARISON = 'route_comparison';
    case SYSTEM_HEALTH = 'system_health';
    case INFRASTRUCTURE_METRICS = 'infrastructure_metrics';
    case DASHBOARD_KPIS = 'dashboard_kpis';
    case MINI_REPORTS = 'mini_reports';
    case ACTIVITY_FEED = 'activity_feed';
}
