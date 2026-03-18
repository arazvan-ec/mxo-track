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
    <div className="bg-slate-800/80 rounded-lg px-2.5 py-2 text-center border border-slate-700/50">
      <div className={`font-bold text-lg leading-none ${className}`}>
        {value ?? '--'}
      </div>
      <div className="text-slate-500 text-[9px] uppercase tracking-wider mt-1">
        {label}
      </div>
    </div>
  );
}
