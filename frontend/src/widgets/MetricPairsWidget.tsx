import { MetricPairs } from '@/components/metrics/MetricPairs';
import type { WidgetProps } from './types';

interface MetricPairsData {
  metrics?: {
    routeCount?: number;
    stopCount?: number;
    distanceBeforeKm?: number;
    distanceAfterKm?: number;
    savedPercent?: number;
    durationBeforeMinutes?: number;
    totalDurationMinutes?: number;
    timeSavedPercent?: number;
    // Generic route detail metrics
    drivingTimeMinutes?: number;
    deliveryTimeMinutes?: number;
    totalTimeMinutes?: number;
    savingsPercent?: number;
  };
  /** Alternative: route-level metrics from RouteData.metrics */
  route?: {
    metrics?: Record<string, unknown>;
    stops?: Array<{ isOrigin?: boolean }>;
  };
}

export function MetricPairsWidget({ data, expanded = false }: WidgetProps) {
  const { metrics: directMetrics, route } = data as MetricPairsData;

  // Try direct metrics first, then extract from route
  let metrics = directMetrics;
  if (!metrics && route?.metrics) {
    const m = route.metrics as Record<string, number>;
    metrics = {
      routeCount: 1,
      stopCount: route.stops?.filter((s) => !s.isOrigin).length ?? 0,
      distanceBeforeKm: m.distanceBeforeKm,
      distanceAfterKm: m.distanceAfterKm,
      savedPercent: m.savingsPercent ?? m.savedPercent,
      durationBeforeMinutes: m.durationBeforeMinutes ?? m.drivingTimeMinutes,
      totalDurationMinutes: m.totalDurationMinutes ?? m.totalTimeMinutes,
      timeSavedPercent: m.timeSavedPercent,
    };
  }

  if (!metrics) return null;

  // Provide defaults for required fields
  const safeMetrics = {
    routeCount: metrics.routeCount ?? 1,
    stopCount: metrics.stopCount ?? 0,
    distanceBeforeKm: metrics.distanceBeforeKm ?? 0,
    distanceAfterKm: metrics.distanceAfterKm ?? 0,
    savedPercent: metrics.savedPercent ?? metrics.savingsPercent ?? 0,
    durationBeforeMinutes: metrics.durationBeforeMinutes ?? 0,
    totalDurationMinutes: metrics.totalDurationMinutes ?? metrics.totalTimeMinutes ?? 0,
    timeSavedPercent: metrics.timeSavedPercent ?? 0,
  };

  return <MetricPairs metrics={safeMetrics} expanded={expanded} />;
}
