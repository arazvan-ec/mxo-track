import type { ComponentType } from 'react';
import type { WidgetType } from '@/types/layout';
import type { WidgetProps } from './types';
import { MetricPairsWidget } from './MetricPairsWidget';
import { RouteCardListWidget } from './RouteCardListWidget';
import { MapLegendWidget } from './MapLegendWidget';
import { RouteComparisonWidget } from './RouteComparisonWidget';
import { KpiPillsWidget } from './KpiPillsWidget';
import { StopListWidget } from './StopListWidget';
import { VehicleInfoWidget } from './VehicleInfoWidget';
import { DriverInfoWidget } from './DriverInfoWidget';
import { ShipmentDetailsWidget } from './ShipmentDetailsWidget';
import { DeliveryTimelineWidget } from './DeliveryTimelineWidget';

export interface WidgetRegistryEntry {
  component: ComponentType<WidgetProps>;
  label: string;
  description: string;
}

export const WIDGET_REGISTRY: Record<WidgetType, WidgetRegistryEntry> = {
  metric_pairs: {
    component: MetricPairsWidget,
    label: 'Metric Pairs',
    description: 'Key metrics in paired format (scope, distance, time)',
  },
  route_card_list: {
    component: RouteCardListWidget,
    label: 'Route Card List',
    description: 'Scrollable list of route cards with stops and metrics',
  },
  map_legend: {
    component: MapLegendWidget,
    label: 'Map Legend',
    description: 'Map legend showing route colors and markers',
  },
  route_comparison: {
    component: RouteComparisonWidget,
    label: 'Route Comparison',
    description: 'Before/after comparison of original vs optimized routes',
  },
  kpi_pills: {
    component: KpiPillsWidget,
    label: 'KPI Pills',
    description: 'Compact KPI indicators (vehicles, routes, pending)',
  },
  stop_list: {
    component: StopListWidget,
    label: 'Stop List',
    description: 'Ordered list of stops with status, ETA, and selection',
  },
  vehicle_info: {
    component: VehicleInfoWidget,
    label: 'Vehicle Info',
    description: 'Vehicle details with driver, speed, and skills',
  },
  driver_info: {
    component: DriverInfoWidget,
    label: 'Driver Info',
    description: 'Driver progress bar with current stop info',
  },
  shipment_details: {
    component: ShipmentDetailsWidget,
    label: 'Shipment Details',
    description: 'Shipment information card with recipient details',
  },
  delivery_timeline: {
    component: DeliveryTimelineWidget,
    label: 'Delivery Timeline',
    description: 'Vertical timeline of delivery events',
  },
};

/** All widget types with labels for the gallery/editor */
export const ALL_WIDGET_TYPES: { type: WidgetType; label: string; description: string }[] =
  Object.entries(WIDGET_REGISTRY).map(([type, entry]) => ({
    type: type as WidgetType,
    label: entry.label,
    description: entry.description,
  }));
