import type { WidgetProps } from './types';

interface DashboardKpisData {
  metrics?: {
    active_routes: number;
    pending_stops: number;
    import_runs_today: number;
    positions_ingested_last_hour: number;
  };
}

interface KpiCardProps {
  label: string;
  value: number;
  bgClass: string;
  textClass: string;
  barClass: string;
  icon: React.ReactNode;
}

function KpiCard({ label, value, bgClass, textClass, barClass, icon }: KpiCardProps) {
  return (
    <div
      className="relative overflow-hidden rounded-xl shadow-sm ring-1"
      style={{ backgroundColor: 'var(--color-surface-elevated)', borderColor: 'var(--color-border)' }}
    >
      <div className="p-6">
        <div className="flex items-center gap-4">
          <div className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-lg ${bgClass}`}>
            <span className={textClass}>{icon}</span>
          </div>
          <div className="min-w-0 flex-1">
            <p className="text-sm font-medium truncate" style={{ color: 'var(--color-text-secondary)' }}>{label}</p>
            <p className="mt-1 text-2xl font-bold tracking-tight tabular-nums" style={{ color: 'var(--color-text-primary)' }}>{value}</p>
          </div>
        </div>
      </div>
      <div className={`absolute bottom-0 left-0 right-0 h-1 ${barClass}`} />
    </div>
  );
}

const RouteIcon = () => (
  <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
    <path strokeLinecap="round" strokeLinejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z" />
  </svg>
);

const ClockIcon = () => (
  <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
    <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
  </svg>
);

const UploadIcon = () => (
  <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
    <path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
  </svg>
);

const SignalIcon = () => (
  <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
    <path strokeLinecap="round" strokeLinejoin="round" d="M7.5 7.5h-.75A2.25 2.25 0 004.5 9.75v7.5a2.25 2.25 0 002.25 2.25h7.5a2.25 2.25 0 002.25-2.25v-7.5a2.25 2.25 0 00-2.25-2.25h-.75m0-3l-3-3m0 0l-3 3m3-3v11.25m6-2.25h.75a2.25 2.25 0 012.25 2.25v7.5a2.25 2.25 0 01-2.25 2.25h-7.5a2.25 2.25 0 01-2.25-2.25v-.75" />
  </svg>
);

export function DashboardKpisWidget({ data }: WidgetProps) {
  const { metrics } = data as DashboardKpisData;
  if (!metrics) return null;

  return (
    <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
      <KpiCard label="Rutas activas" value={metrics.active_routes} bgClass="bg-indigo-50" textClass="text-indigo-600" barClass="bg-indigo-400" icon={<RouteIcon />} />
      <KpiCard label="Paradas pendientes" value={metrics.pending_stops} bgClass="bg-orange-50" textClass="text-orange-600" barClass="bg-orange-400" icon={<ClockIcon />} />
      <KpiCard label="Imports CSV hoy" value={metrics.import_runs_today} bgClass="bg-violet-50" textClass="text-violet-600" barClass="bg-violet-400" icon={<UploadIcon />} />
      <KpiCard label="Posiciones (última hora)" value={metrics.positions_ingested_last_hour} bgClass="bg-cyan-50" textClass="text-cyan-600" barClass="bg-cyan-400" icon={<SignalIcon />} />
    </div>
  );
}
