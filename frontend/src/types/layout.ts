export type WidgetType =
  | 'metric_pairs'
  | 'route_card_list'
  | 'stop_list'
  | 'vehicle_info'
  | 'driver_info'
  | 'shipment_details'
  | 'delivery_timeline'
  | 'kpi_pills'
  | 'map_legend'
  | 'route_comparison'
  | 'system_health'
  | 'infrastructure_metrics'
  | 'dashboard_kpis'
  | 'mini_reports'
  | 'activity_feed'
  | 'customer_kpis'
  | 'customer_optimization'
  | 'reports_banner';

export type SheetStateName = 'collapsed' | 'half' | 'full';

export type PageKey =
  | 'fleet_map'
  | 'test_routing'
  | 'route_planner'
  | 'route_analysis'
  | 'route_detail'
  | 'shipment_tracking'
  | 'driver_route'
  | 'customer_tracking'
  | 'admin_dashboard'
  | 'customer_dashboard';

export interface WidgetPlacement {
  type: WidgetType;
  position: number;
}

export interface LayoutConfig {
  pageKey: PageKey;
  scope: 'global' | 'customer' | 'none';
  widgets: Record<SheetStateName, WidgetPlacement[]>;
}
