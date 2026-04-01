import { KpiPills } from '@/components/fleet/KpiPills';
import type { WidgetProps } from './types';

interface KpiPillsData {
  kpi?: { total_vehicles: number; active_routes: number; pending_stops: number };
}

export function KpiPillsWidget({ data }: WidgetProps) {
  const { kpi } = data as KpiPillsData;
  if (!kpi) return null;

  return <KpiPills kpi={kpi} />;
}
