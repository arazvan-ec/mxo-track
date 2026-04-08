import type { FleetKpi } from '@/api/hooks/useFleetKpi';

interface Props {
  kpi: FleetKpi | undefined;
}

export function KpiPills({ kpi }: Props) {
  return (
    <div className="grid grid-cols-3 gap-2">
      <Pill label="Vehicles" value={kpi?.total_vehicles} />
      <Pill label="Routes" value={kpi?.active_routes} className="text-blue-400" />
      <Pill label="Pending" value={kpi?.pending_stops} className="text-amber-400" />
    </div>
  );
}

function Pill({
  label,
  value,
  className = 'text-white',
}: {
  label: string;
  value: number | undefined;
  className?: string;
}) {
  return (
    <div className="theme-card-overlay px-2.5 py-2 text-center">
      <div className="font-bold text-lg leading-none" style={{ color: className === 'text-white' ? 'var(--color-text-primary)' : className === 'text-blue-400' ? 'var(--color-accent)' : 'var(--color-warning)' }}>
        {value ?? '--'}
      </div>
      <div className="text-[9px] uppercase tracking-wider mt-1" style={{ color: 'var(--color-text-muted)' }}>
        {label}
      </div>
    </div>
  );
}
