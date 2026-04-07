import { useAdminDashboard } from '@/api/hooks/useAdminDashboard';
import type {
  HealthStatus,
  LiveData,
  LiveServiceData,
  DailyDelivery,
  TopDriver,
} from '@/api/types';

/* ── Helper ───────────────────────────────────────────────────────── */

function formatSecondsAgo(seconds: number | null): string {
  if (seconds === null) return 'Sin datos';
  if (seconds < 60) return `${seconds}s`;
  if (seconds < 3600) return `${Math.floor(seconds / 60)}m`;
  return `${Math.floor(seconds / 3600)}h ${Math.floor((seconds % 3600) / 60)}m`;
}

/* ── Sub-components ───────────────────────────────────────────────── */

function ServiceHealthCard({
  label,
  ok,
  latencyMs,
  warnThreshold,
}: {
  label: string;
  ok: boolean;
  latencyMs: number;
  warnThreshold: number;
}) {
  return (
    <div
      className="rounded-xl p-4 shadow-sm ring-1"
      style={{
        backgroundColor: 'var(--color-surface-elevated)',
        borderColor: 'var(--color-border)',
      }}
    >
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div
            className="flex h-10 w-10 items-center justify-center rounded-lg"
            style={{ backgroundColor: ok ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)' }}
          >
            <div
              className="h-3 w-3 rounded-full"
              style={{ backgroundColor: ok ? 'var(--color-success)' : 'var(--color-error)' }}
            />
          </div>
          <div>
            <p className="text-sm font-medium" style={{ color: 'var(--color-text-primary)' }}>
              {label}
            </p>
            <p
              className="text-xs"
              style={{ color: ok ? 'var(--color-success)' : 'var(--color-error)' }}
            >
              {ok ? 'Conectado' : 'Sin conexion'}
            </p>
          </div>
        </div>
        <span
          className="text-xs font-mono tabular-nums"
          style={{
            color: latencyMs > warnThreshold ? 'var(--color-warning)' : 'var(--color-text-muted)',
          }}
        >
          {latencyMs}ms
        </span>
      </div>
    </div>
  );
}

function KpiCard({
  label,
  value,
  color,
}: {
  label: string;
  value: number;
  color: string;
}) {
  return (
    <div
      className="relative overflow-hidden rounded-xl shadow-sm ring-1"
      style={{ backgroundColor: 'var(--color-surface-elevated)', borderColor: 'var(--color-border)' }}
    >
      <div className="p-6">
        <p className="text-sm font-medium" style={{ color: 'var(--color-text-secondary)' }}>
          {label}
        </p>
        <p
          className="mt-1 text-2xl font-bold tracking-tight"
          style={{ color: 'var(--color-text-primary)' }}
        >
          {value.toLocaleString('es-ES')}
        </p>
      </div>
      <div className="absolute bottom-0 left-0 right-0 h-1" style={{ backgroundColor: color }} />
    </div>
  );
}

function MiniBarChart({ data }: { data: DailyDelivery[] }) {
  const max = Math.max(...data.map((d) => d.deliveries), 1);

  return (
    <div
      className="rounded-xl p-6 shadow-sm ring-1"
      style={{ backgroundColor: 'var(--color-surface-elevated)', borderColor: 'var(--color-border)' }}
    >
      <h3 className="text-base font-semibold mb-4" style={{ color: 'var(--color-text-primary)' }}>
        Entregas (ultimos 7 dias)
      </h3>
      <div className="flex items-end gap-2" style={{ height: 140 }}>
        {data.map((d) => (
          <div key={d.date} className="flex-1 flex flex-col items-center gap-1">
            <span className="text-xs font-mono tabular-nums" style={{ color: 'var(--color-text-muted)' }}>
              {d.deliveries}
            </span>
            <div
              className="w-full rounded-t"
              style={{
                height: `${(d.deliveries / max) * 100}%`,
                minHeight: 4,
                backgroundColor: 'var(--color-accent)',
              }}
            />
            <span className="text-[10px]" style={{ color: 'var(--color-text-muted)' }}>
              {new Date(d.date).toLocaleDateString('es-ES', { weekday: 'short' })}
            </span>
          </div>
        ))}
      </div>
    </div>
  );
}

function TopDriversList({ drivers }: { drivers: TopDriver[] }) {
  const medals = ['#f59e0b', '#9ca3af', '#f97316'];

  return (
    <div
      className="rounded-xl shadow-sm ring-1 overflow-hidden"
      style={{ backgroundColor: 'var(--color-surface-elevated)', borderColor: 'var(--color-border)' }}
    >
      <div className="px-6 py-4 border-b" style={{ borderColor: 'var(--color-border)' }}>
        <h3 className="text-base font-semibold" style={{ color: 'var(--color-text-primary)' }}>
          Top transportistas (esta semana)
        </h3>
      </div>
      {drivers.length > 0 ? (
        <div>
          {drivers.map((driver, i) => (
            <div
              key={driver.driver_email}
              className="flex items-center gap-4 px-6 py-3 border-b last:border-b-0"
              style={{ borderColor: 'var(--color-border)' }}
            >
              <span
                className="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                style={{
                  backgroundColor: medals[i] ? `${medals[i]}20` : 'var(--color-accent-muted)',
                  color: medals[i] ?? 'var(--color-text-secondary)',
                }}
              >
                {i + 1}
              </span>
              <div className="min-w-0 flex-1">
                <p className="text-sm font-medium truncate" style={{ color: 'var(--color-text-primary)' }}>
                  {driver.driver_name}
                </p>
                <p className="text-xs truncate" style={{ color: 'var(--color-text-secondary)' }}>
                  {driver.driver_email}
                </p>
              </div>
              <span
                className="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                style={{ backgroundColor: 'rgba(16,185,129,0.1)', color: 'var(--color-success)' }}
              >
                {driver.deliveries} entregas
              </span>
            </div>
          ))}
        </div>
      ) : (
        <div className="px-6 py-8 text-center text-sm" style={{ color: 'var(--color-text-secondary)' }}>
          Sin datos de entregas esta semana.
        </div>
      )}
    </div>
  );
}

function InfrastructureMetrics({ live }: { live: LiveData }) {
  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <div
        className="rounded-xl p-4 shadow-sm ring-1"
        style={{ backgroundColor: 'var(--color-surface-elevated)', borderColor: 'var(--color-border)' }}
      >
        <p className="text-sm font-medium" style={{ color: 'var(--color-text-primary)' }}>
          Posiciones (tabla)
        </p>
        <p
          className="text-lg font-bold tabular-nums"
          style={{ color: live.positions.warning ? 'var(--color-warning)' : 'var(--color-text-primary)' }}
        >
          {Number(live.positions.row_count).toLocaleString('es-ES')}
        </p>
        {live.positions.warning && (
          <p className="text-xs" style={{ color: 'var(--color-warning)' }}>
            Excede 1M filas - considerar purge
          </p>
        )}
      </div>

      <div
        className="rounded-xl p-4 shadow-sm ring-1"
        style={{ backgroundColor: 'var(--color-surface-elevated)', borderColor: 'var(--color-border)' }}
      >
        <p className="text-sm font-medium" style={{ color: 'var(--color-text-primary)' }}>
          Base de datos
        </p>
        <p className="text-lg font-bold tabular-nums" style={{ color: 'var(--color-text-primary)' }}>
          {live.disk.db_size_mb} MB
        </p>
      </div>

      <div
        className="rounded-xl p-4 shadow-sm ring-1"
        style={{ backgroundColor: 'var(--color-surface-elevated)', borderColor: 'var(--color-border)' }}
      >
        <p className="text-sm font-medium" style={{ color: 'var(--color-text-primary)' }}>
          Ultima ingestion
        </p>
        {live.last_ingestion.timestamp ? (
          <>
            <p className="text-sm font-bold tabular-nums" style={{ color: 'var(--color-text-primary)' }}>
              {formatSecondsAgo(live.last_ingestion.seconds_ago)}
            </p>
            <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
              {new Date(live.last_ingestion.timestamp).toLocaleString('es-ES')}
            </p>
          </>
        ) : (
          <p className="text-sm" style={{ color: 'var(--color-text-muted)' }}>Sin datos</p>
        )}
      </div>
    </div>
  );
}

/* ── Main Page ────────────────────────────────────────────────────── */

const SERVICE_CONFIG: Array<{
  key: keyof LiveData;
  label: string;
  healthKey: keyof HealthStatus;
  warnMs: number;
}> = [
  { key: 'database', label: 'PostgreSQL', healthKey: 'db_ok', warnMs: 100 },
  { key: 'redis', label: 'Redis', healthKey: 'redis_ok', warnMs: 50 },
  { key: 'traccar', label: 'Traccar API', healthKey: 'traccar_ok', warnMs: 500 },
  { key: 'mercure', label: 'Mercure Hub', healthKey: 'mercure_ok', warnMs: 500 },
  { key: 'osrm', label: 'OSRM (Routing)', healthKey: 'osrm_ok', warnMs: 500 },
  { key: 'vroom', label: 'VROOM (Optimizer)', healthKey: 'vroom_ok', warnMs: 500 },
];

export function AdminDashboardPage() {
  const { data, isLoading, error } = useAdminDashboard();

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div
          className="animate-spin h-8 w-8 border-2 rounded-full border-t-transparent"
          style={{ borderColor: 'var(--color-accent)', borderTopColor: 'transparent' }}
        />
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="p-8 text-center">
        <p style={{ color: 'var(--color-error)' }}>Error cargando dashboard</p>
      </div>
    );
  }

  const { health, live, metrics, daily_deliveries, top_drivers } = data;

  return (
    <div className="h-full overflow-y-auto p-6 lg:p-8 space-y-8" style={{ backgroundColor: 'var(--color-surface)' }}>
      {/* Page header */}
      <div>
        <h1 className="text-2xl font-bold tracking-tight" style={{ color: 'var(--color-text-primary)' }}>
          Dashboard
        </h1>
        <p className="mt-1 text-sm" style={{ color: 'var(--color-text-secondary)' }}>
          Vista general del sistema de seguimiento logistico
        </p>
      </div>

      {/* System Health Status */}
      <section>
        <h2
          className="text-sm font-semibold uppercase tracking-wider mb-3"
          style={{ color: 'var(--color-text-muted)' }}
        >
          Estado del sistema
        </h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          {SERVICE_CONFIG.map((svc) => (
            <ServiceHealthCard
              key={svc.key}
              label={svc.label}
              ok={health[svc.healthKey]}
              latencyMs={(live[svc.key] as LiveServiceData).latency_ms}
              warnThreshold={svc.warnMs}
            />
          ))}
        </div>
      </section>

      {/* Infrastructure Metrics */}
      <section>
        <h2
          className="text-sm font-semibold uppercase tracking-wider mb-3"
          style={{ color: 'var(--color-text-muted)' }}
        >
          Infraestructura
        </h2>
        <InfrastructureMetrics live={live} />
      </section>

      {/* KPI Cards */}
      <section>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
          <KpiCard label="Rutas activas" value={metrics.active_routes} color="var(--color-accent)" />
          <KpiCard label="Paradas pendientes" value={metrics.pending_stops} color="var(--color-warning)" />
          <KpiCard label="Imports CSV hoy" value={metrics.import_runs_today} color="#8b5cf6" />
          <KpiCard
            label="Posiciones (ultima hora)"
            value={metrics.positions_ingested_last_hour}
            color="#06b6d4"
          />
        </div>
      </section>

      {/* Mini Reports */}
      <section className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <MiniBarChart data={daily_deliveries} />
        <TopDriversList drivers={top_drivers} />
      </section>

      {/* Reports banner */}
      <section>
        <a
          href="/admin/reports"
          className="group flex items-center justify-between rounded-xl p-5 shadow-sm transition-all hover:shadow-md"
          style={{ background: 'linear-gradient(to right, var(--color-accent), #6366f1)' }}
        >
          <div>
            <p className="text-sm font-semibold text-white">Reportes y Analitica</p>
            <p className="text-xs text-white/70">
              Accede a reportes detallados de entregas, transportistas y clientes
            </p>
          </div>
          <svg
            className="h-5 w-5 text-white/70 transition-transform group-hover:translate-x-1"
            fill="none"
            viewBox="0 0 24 24"
            strokeWidth="2"
            stroke="currentColor"
          >
            <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
          </svg>
        </a>
      </section>
    </div>
  );
}
