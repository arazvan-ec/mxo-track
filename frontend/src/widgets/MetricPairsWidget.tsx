import { MetricPairs } from '@/components/metrics/MetricPairs';
import type { TestRoutingMetrics } from '@/api/hooks/useTestRoutingData';
import type { WidgetProps } from './types';

interface MetricPairsData {
  metrics: TestRoutingMetrics;
}

export function MetricPairsWidget({ data, expanded = false }: WidgetProps) {
  const { metrics } = data as MetricPairsData;
  if (!metrics) return null;

  return <MetricPairs metrics={metrics} expanded={expanded} />;
}
