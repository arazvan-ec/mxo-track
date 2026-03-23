import type { ComponentType } from 'react';
import type { WidgetType } from '@/types/layout';
import type { WidgetProps } from './types';
import { MetricPairsWidget } from './MetricPairsWidget';
import { RouteCardListWidget } from './RouteCardListWidget';
import { MapLegendWidget } from './MapLegendWidget';
import { RouteComparisonWidget } from './RouteComparisonWidget';

export interface WidgetRegistryEntry {
  component: ComponentType<WidgetProps>;
  label: string;
  description: string;
}

export const WIDGET_REGISTRY: Partial<Record<WidgetType, WidgetRegistryEntry>> = {
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
};

/** All widget types with labels for the gallery/editor */
export const ALL_WIDGET_TYPES: { type: WidgetType; label: string; description: string }[] = [
  { type: 'metric_pairs', label: 'Metric Pairs', description: 'Key metrics in paired format' },
  { type: 'route_card_list', label: 'Route Card List', description: 'Route cards with stops' },
  { type: 'stop_list', label: 'Stop List', description: 'Ordered list of stops with status' },
  { type: 'vehicle_info', label: 'Vehicle Info', description: 'Vehicle details panel' },
  { type: 'driver_info', label: 'Driver Info', description: 'Driver details panel' },
  { type: 'shipment_details', label: 'Shipment Details', description: 'Shipment information' },
  { type: 'delivery_timeline', label: 'Delivery Timeline', description: 'Timeline of delivery events' },
  { type: 'kpi_pills', label: 'KPI Pills', description: 'Compact KPI indicators' },
  { type: 'map_legend', label: 'Map Legend', description: 'Route colors and markers legend' },
  { type: 'route_comparison', label: 'Route Comparison', description: 'Original vs optimized comparison' },
];
