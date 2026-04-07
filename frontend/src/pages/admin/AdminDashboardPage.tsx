import { useAdminDashboard } from '@/api/hooks/useAdminDashboard';
import { useMe } from '@/api/hooks/useMe';
import type {
  HealthStatus,
  LiveData,
  LiveServiceData,
} from '@/api/types';
import { AnimatedCounter } from '@/components/ui/AnimatedCounter';
import { SparklineSVG } from '@/components/ui/SparklineSVG';
import { RadialGauge } from '@/components/ui/RadialGauge';
import { ThemeSwitcher } from '@/components/ui/ThemeSwitcher';

/* ── Helpers ─────────────────────────────────────────────────────── */

function getGreeting(): string {
  const h = new Date().getHours();
  if (h < 12) return 'Buenos dias';
  if (h < 19) return 'Buenas tardes';
  return 'Buenas noches';
}

function formatDate(): string {
  return new Date().toLocaleDateString('es-ES', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
}

function formatSecondsAgo(seconds: number | null): string {
  if (seconds === null) return 'Sin datos';
  if (seconds < 60) return `hace ${seconds}s`;
  if (seconds < 3600) return `hace ${Math.floor(seconds / 60)}min`;
  return `hace ${Math.floor(seconds / 3600)}h`;
}

/* ── Service Health Row ──────────────────────────────────────────── */

const SERVICE_CONFIG: Array<{
  key: keyof LiveData;
  label: string;
  healthKey: keyof HealthStatus;
  warnMs: number;
  maxMs: number;
}> = [
  { key: 'database', label: 'PostgreSQL', healthKey: 'db_ok', warnMs: 100, maxMs: 200 },
  { key: 'redis', label: 'Redis', healthKey: 'redis_ok', warnMs: 50, maxMs: 100 },
  { key: 'traccar', label: 'Traccar', healthKey: 'traccar_ok', warnMs: 500, maxMs: 1000 },
  { key: 'mercure', label: 'Mercure', healthKey: 'mercure_ok', warnMs: 500, maxMs: 1000 },
  { key: 'osrm', label: 'OSRM', healthKey: 'osrm_ok', warnMs: 500, maxMs: 1000 },
  { key: 'vroom', label: 'VROOM', healthKey: 'vroom_ok', warnMs: 500, maxMs: 2000 },
];

/* ── Main Page ───────────────────────────────────────────────────── */

export function AdminDashboardPage() {
  const { data, isLoading, error } = useAdminDashboard();
  const { data: me } = useMe();

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
  const firstName = me?.email?.split('@')[0] ?? '';
  const sparkData = daily_deliveries.map((d) => d.deliveries);
  const totalDeliveries7d = daily_deliveries.reduce((sum, d) => sum + d.deliveries, 0);
  const maxDeliveries = Math.max(...daily_deliveries.map((d) => d.deliveries), 1);
  const allHealthy = SERVICE_CONFIG.every((s) => health[s.healthKey]);
  const healthyCount = SERVICE_CONFIG.filter((s) => health[s.healthKey]).length;

  return (
    <div
      className="h-full overflow-y-auto"
      style={{ backgroundColor: 'var(--color-surface)' }}
    >
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6" style={{ gap: 'var(--section-gap)' }}>

        {/* ── Greeting Header ── */}
        <div className="animate-fade-in-up">
          <h1 className="text-2xl sm:text-3xl font-bold tracking-tight" style={{ color: 'var(--color-text-primary)' }}>
            {getGreeting()}{firstName ? `, ${firstName}` : ''}
          </h1>
          <p className="mt-1 text-sm" style={{ color: 'var(--color-text-secondary)' }}>
            {formatDate()} · Actualizado {formatSecondsAgo(live.last_ingestion.seconds_ago)}
          </p>
        </div>

        {/* ── Bento Grid: Top Row — KPIs + System Status ── */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-4" style={{ gap: 'var(--section-gap, 1rem)' }}>

          {/* Left 2/3: KPI Cards */}
          <div className="lg:col-span-2 grid grid-cols-2 sm:grid-cols-4 gap-3" style={{ gap: 'var(--section-gap, 0.75rem)' }}>
            {[
              { label: 'Rutas activas', value: metrics.active_routes, color: 'var(--color-accent)' },
              { label: 'Paradas pendientes', value: metrics.pending_stops, color: 'var(--color-warning)' },
              { label: 'Imports hoy', value: metrics.import_runs_today, color: '#8b5cf6' },
              { label: 'Posiciones/h', value: metrics.positions_ingested_last_hour, color: '#06b6d4' },
            ].map((kpi, i) => (
              <div
                key={kpi.label}
                className="theme-card animate-fade-in-up"
                style={{ padding: 'var(--card-padding)', animationDelay: `${i * 60}ms` }}
              >
                <p className="text-xs font-medium" style={{ color: 'var(--color-text-secondary)' }}>{kpi.label}</p>
                <AnimatedCounter
                  value={kpi.value}
                  className="text-2xl font-bold tabular-nums block mt-1"
                  style={{ color: 'var(--color-text-primary)', fontFamily: 'var(--kpi-font)' }}
                />
                <div className="mt-2 h-0.5 rounded-full" style={{ background: `linear-gradient(to right, ${kpi.color}, transparent)` }} />
              </div>
            ))}
          </div>

          {/* Right 1/3: System Health Summary */}
          <div
            className="theme-card animate-fade-in-up"
            style={{ padding: 'var(--card-padding)', animationDelay: '240ms' }}
          >
            <div className="flex items-center justify-between mb-3">
              <h2 className="text-xs font-semibold uppercase tracking-wider" style={{ color: 'var(--color-text-muted)' }}>
                Sistema
              </h2>
              <span
                className="text-xs font-medium px-2 py-0.5 rounded-full"
                style={{
                  backgroundColor: allHealthy ? 'var(--color-accent-muted)' : 'rgba(239,68,68,0.1)',
                  color: allHealthy ? 'var(--color-success)' : 'var(--color-error)',
                }}
              >
                {healthyCount}/{SERVICE_CONFIG.length} OK
              </span>
            </div>
            <div className="grid grid-cols-3 gap-2">
              {SERVICE_CONFIG.map((svc) => {
                const liveData = live[svc.key] as LiveServiceData;
                const ok = health[svc.healthKey];
                return (
                  <div key={svc.key} className="flex flex-col items-center gap-1">
                    <RadialGauge
                      value={liveData.latency_ms}
                      max={svc.maxMs}
                      size={44}
                      strokeWidth={3}
                      warnThreshold={svc.warnMs}
                      critThreshold={svc.maxMs * 0.8}
                    />
                    <span className="text-[10px] font-medium truncate w-full text-center" style={{ color: ok ? 'var(--color-text-secondary)' : 'var(--color-error)' }}>
                      {svc.label}
                    </span>
                  </div>
                );
              })}
            </div>
          </div>
        </div>

        {/* ── Bento Grid: Middle Row — Charts ── */}
        <div className="grid grid-cols-1 lg:grid-cols-5 gap-4" style={{ gap: 'var(--section-gap, 1rem)' }}>

          {/* Delivery Trend (3/5) */}
          <div
            className="lg:col-span-3 theme-card animate-fade-in-up"
            style={{ padding: 'var(--card-padding)', animationDelay: '300ms' }}
          >
            <div className="flex items-center justify-between mb-4">
              <div>
                <h2 className="text-sm font-semibold" style={{ color: 'var(--color-text-primary)' }}>
                  Entregas (7 dias)
                </h2>
                <p className="text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                  Total: <span className="font-bold" style={{ fontFamily: 'var(--data-font)' }}>{totalDeliveries7d}</span>
                </p>
              </div>
              <SparklineSVG data={sparkData} width={100} height={28} color="var(--color-accent)" />
            </div>
            <div className="flex items-end gap-2" style={{ height: 140 }}>
              {daily_deliveries.map((d, i) => (
                <div key={d.date} className="flex-1 flex flex-col items-center gap-1">
                  <span
                    className="text-xs tabular-nums"
                    style={{ color: 'var(--color-text-muted)', fontFamily: 'var(--data-font)' }}
                  >
                    {d.deliveries}
                  </span>
                  <div
                    className="w-full rounded-t animate-bar-grow"
                    style={{
                      height: `${(d.deliveries / maxDeliveries) * 100}%`,
                      minHeight: 2,
                      background: `linear-gradient(to top, var(--color-accent), var(--color-accent-hover))`,
                      animationDelay: `${400 + i * 80}ms`,
                    }}
                  />
                  <span className="text-[10px] truncate w-full text-center" style={{ color: 'var(--color-text-muted)' }}>
                    {new Date(d.date).toLocaleDateString('es-ES', { weekday: 'short' })}
                  </span>
                </div>
              ))}
            </div>
          </div>

          {/* Top Drivers (2/5) */}
          <div
            className="lg:col-span-2 theme-card animate-fade-in-up overflow-hidden"
            style={{ animationDelay: '360ms' }}
          >
            <div className="px-4 py-3 border-b" style={{ borderColor: 'var(--color-border)' }}>
              <h2 className="text-sm font-semibold" style={{ color: 'var(--color-text-primary)' }}>
                Top transportistas
              </h2>
            </div>
            <div className="p-3 space-y-1">
              {top_drivers.length > 0 ? (
                top_drivers.map((driver, i) => {
                  const maxDel = Math.max(...top_drivers.map((d) => d.deliveries), 1);
                  const pct = (driver.deliveries / maxDel) * 100;
                  const medals = ['var(--color-warning)', '#94a3b8', '#f97316'];
                  return (
                    <div key={driver.driver_email} className="flex items-center gap-3 py-1.5 px-2 rounded-lg animate-fade-in-up" style={{ animationDelay: `${420 + i * 60}ms` }}>
                      <span
                        className="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold"
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
                        {/* Progress bar */}
                        <div className="mt-1 h-1 rounded-full overflow-hidden" style={{ backgroundColor: 'var(--color-border)' }}>
                          <div
                            className="h-full rounded-full transition-all duration-700"
                            style={{ width: `${pct}%`, backgroundColor: medals[i] ?? 'var(--color-accent)' }}
                          />
                        </div>
                      </div>
                      <span className="text-xs font-bold tabular-nums" style={{ color: 'var(--color-accent)', fontFamily: 'var(--data-font)' }}>
                        {driver.deliveries}
                      </span>
                    </div>
                  );
                })
              ) : (
                <p className="text-sm text-center py-6" style={{ color: 'var(--color-text-muted)' }}>Sin datos esta semana</p>
              )}
            </div>
          </div>
        </div>

        {/* ── Bottom Row: Infrastructure ── */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4" style={{ gap: 'var(--section-gap, 1rem)' }}>
          {/* Positions */}
          <div className="theme-card animate-fade-in-up" style={{ padding: 'var(--card-padding)', animationDelay: '480ms' }}>
            <div className="flex items-center gap-2 mb-2">
              <svg className="h-4 w-4" style={{ color: live.positions.warning ? 'var(--color-warning)' : 'var(--color-accent)' }} fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5" />
              </svg>
              <span className="text-xs font-medium" style={{ color: 'var(--color-text-secondary)' }}>Posiciones (tabla)</span>
            </div>
            <AnimatedCounter
              value={live.positions.row_count}
              className="text-xl font-bold tabular-nums"
              style={{ color: live.positions.warning ? 'var(--color-warning)' : 'var(--color-text-primary)', fontFamily: 'var(--data-font)' }}
            />
            <div className="mt-2 h-1.5 rounded-full overflow-hidden" style={{ backgroundColor: 'var(--color-border)' }}>
              <div
                className="h-full rounded-full transition-all duration-1000"
                style={{
                  width: `${Math.min((live.positions.row_count / 1_000_000) * 100, 100)}%`,
                  backgroundColor: live.positions.warning ? 'var(--color-warning)' : 'var(--color-accent)',
                }}
              />
            </div>
          </div>

          {/* DB Size */}
          <div className="theme-card animate-fade-in-up" style={{ padding: 'var(--card-padding)', animationDelay: '540ms' }}>
            <div className="flex items-center gap-2 mb-2">
              <svg className="h-4 w-4" style={{ color: 'var(--color-accent)' }} fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375" />
              </svg>
              <span className="text-xs font-medium" style={{ color: 'var(--color-text-secondary)' }}>Base de datos</span>
            </div>
            <AnimatedCounter
              value={live.disk.db_size_mb}
              formatter={(n) => `${n} MB`}
              className="text-xl font-bold tabular-nums"
              style={{ color: 'var(--color-text-primary)', fontFamily: 'var(--data-font)' }}
            />
          </div>

          {/* Last Ingestion */}
          <div className="theme-card animate-fade-in-up" style={{ padding: 'var(--card-padding)', animationDelay: '600ms' }}>
            <div className="flex items-center gap-2 mb-2">
              <span className="relative flex h-2 w-2">
                <span className="absolute inline-flex h-full w-full animate-ping rounded-full opacity-75" style={{ backgroundColor: 'var(--color-success)' }} />
                <span className="relative inline-flex h-2 w-2 rounded-full" style={{ backgroundColor: 'var(--color-success)' }} />
              </span>
              <span className="text-xs font-medium" style={{ color: 'var(--color-text-secondary)' }}>Ultima ingestion</span>
            </div>
            {live.last_ingestion.timestamp ? (
              <>
                <p className="text-xl font-bold" style={{ color: 'var(--color-text-primary)', fontFamily: 'var(--data-font)' }}>
                  {formatSecondsAgo(live.last_ingestion.seconds_ago)}
                </p>
                <p className="text-xs mt-1" style={{ color: 'var(--color-text-muted)' }}>
                  {new Date(live.last_ingestion.timestamp).toLocaleString('es-ES')}
                </p>
              </>
            ) : (
              <p className="text-sm" style={{ color: 'var(--color-text-muted)' }}>Sin datos</p>
            )}
          </div>
        </div>

        {/* ── Reports Banner ── */}
        <a
          href="/admin/reports"
          className="group flex items-center justify-between rounded-2xl p-5 shadow-sm transition-all hover:shadow-md animate-fade-in-up"
          style={{ background: 'linear-gradient(135deg, var(--color-accent), #6366f1)', animationDelay: '660ms' }}
        >
          <div>
            <p className="text-sm font-semibold text-white">Reportes y Analitica</p>
            <p className="text-xs text-white/70">
              Accede a reportes detallados de entregas, transportistas y clientes
            </p>
          </div>
          <svg
            className="h-5 w-5 text-white/70 transition-transform group-hover:translate-x-1"
            fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor"
          >
            <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
          </svg>
        </a>
      </div>

      {/* Theme Switcher */}
      <ThemeSwitcher />
    </div>
  );
}
