import { useCustomerKpis, useCustomerOptimizationKpis } from '@/api/hooks/useCustomerDashboard';
import { useMe } from '@/api/hooks/useMe';
import { AnimatedCounter } from '@/components/ui/AnimatedCounter';

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

function formatMinutes(minutes: number): string {
  if (minutes < 60) return `${minutes}m`;
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  return m > 0 ? `${h}h ${m}m` : `${h}h`;
}

/* ── KPI Config ─────────────────────────────────────────────────── */

const KPI_CONFIG = [
  { key: 'total_shipments' as const, label: 'Envios totales', color: 'var(--color-accent)', icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' },
  { key: 'active_routes' as const, label: 'Rutas activas', color: '#3b82f6', icon: 'M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z' },
  { key: 'pending_deliveries' as const, label: 'Pendientes', color: 'var(--color-warning)', icon: 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z' },
  { key: 'completed_today' as const, label: 'Completadas hoy', color: 'var(--color-success)', icon: 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
  { key: 'exceptions' as const, label: 'Excepciones', color: 'var(--color-error)', icon: 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z' },
];

/* ── Main Page ───────────────────────────────────────────────────── */

export function CustomerDashboardPage() {
  const { data: kpis, isLoading: kpisLoading } = useCustomerKpis();
  const { data: optKpis, isLoading: optLoading } = useCustomerOptimizationKpis();
  const { data: me } = useMe();

  const isLoading = kpisLoading || optLoading;
  const firstName = me?.email?.split('@')[0] ?? '';

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

  return (
    <div className="h-full overflow-y-auto" style={{ backgroundColor: 'var(--color-surface)' }}>
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

        {/* ── Greeting ── */}
        <div className="animate-fade-in-up">
          <h1 className="text-2xl sm:text-3xl font-bold tracking-tight" style={{ color: 'var(--color-text-primary)' }}>
            {getGreeting()}{firstName ? `, ${firstName}` : ''}
          </h1>
          <p className="mt-1 text-sm" style={{ color: 'var(--color-text-secondary)' }}>
            {formatDate()}
          </p>
        </div>

        {/* ── Primary KPIs ── */}
        {kpis && (
          <section>
            <h2 className="text-xs font-semibold uppercase tracking-wider mb-3" style={{ color: 'var(--color-text-muted)' }}>
              Indicadores
            </h2>
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3" style={{ gap: 'var(--section-gap, 0.75rem)' }}>
              {KPI_CONFIG.map((cfg, i) => (
                <div
                  key={cfg.key}
                  className="theme-card animate-fade-in-up"
                  style={{ padding: 'var(--card-padding)', animationDelay: `${i * 60}ms` }}
                >
                  <div className="flex items-start justify-between gap-2">
                    <div className="flex-1 min-w-0">
                      <p className="text-xs font-medium" style={{ color: 'var(--color-text-secondary)' }}>{cfg.label}</p>
                      <AnimatedCounter
                        value={kpis[cfg.key]}
                        className="text-2xl font-bold tabular-nums block mt-1"
                        style={{ color: 'var(--color-text-primary)', fontFamily: 'var(--kpi-font)' }}
                      />
                    </div>
                    <div
                      className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                      style={{ backgroundColor: `${cfg.color}18`, color: cfg.color }}
                    >
                      <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" d={cfg.icon} />
                      </svg>
                    </div>
                  </div>
                  <div className="mt-2 h-0.5 rounded-full" style={{ background: `linear-gradient(to right, ${cfg.color}, transparent)` }} />
                </div>
              ))}
            </div>
          </section>
        )}

        {/* ── Optimization Value ── */}
        {optKpis && (
          <section>
            <h2 className="text-xs font-semibold uppercase tracking-wider mb-3" style={{ color: 'var(--color-text-muted)' }}>
              Valor de optimizacion
            </h2>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3" style={{ gap: 'var(--section-gap, 0.75rem)' }}>
              {/* Km saved */}
              <div className="theme-card animate-fade-in-up" style={{ padding: 'var(--card-padding)', animationDelay: '300ms' }}>
                <p className="text-xs font-medium" style={{ color: 'var(--color-text-secondary)' }}>Km ahorrados (mes)</p>
                <AnimatedCounter
                  value={Math.round(optKpis.monthly_km_saved)}
                  formatter={(n) => `${n} km`}
                  className="text-xl font-bold tabular-nums block mt-1"
                  style={{ color: 'var(--color-success)', fontFamily: 'var(--kpi-font)' }}
                />
                <p className="text-xs mt-1" style={{ color: 'var(--color-text-muted)' }}>
                  Total: {Math.round(optKpis.total_km_saved).toLocaleString('es-ES')} km
                </p>
              </div>

              {/* Time saved */}
              <div className="theme-card animate-fade-in-up" style={{ padding: 'var(--card-padding)', animationDelay: '360ms' }}>
                <p className="text-xs font-medium" style={{ color: 'var(--color-text-secondary)' }}>Tiempo ahorrado (mes)</p>
                <p className="text-xl font-bold tabular-nums mt-1" style={{ color: 'var(--color-accent)', fontFamily: 'var(--kpi-font)' }}>
                  {formatMinutes(optKpis.monthly_time_saved_minutes)}
                </p>
                <p className="text-xs mt-1" style={{ color: 'var(--color-text-muted)' }}>
                  Total: {formatMinutes(optKpis.total_time_saved_minutes)}
                </p>
              </div>

              {/* Success rate */}
              <div className="theme-card animate-fade-in-up" style={{ padding: 'var(--card-padding)', animationDelay: '420ms' }}>
                <p className="text-xs font-medium" style={{ color: 'var(--color-text-secondary)' }}>Tasa de exito</p>
                {optKpis.avg_delivery_success_rate !== null ? (
                  <AnimatedCounter
                    value={Math.round(optKpis.avg_delivery_success_rate)}
                    formatter={(n) => `${n}%`}
                    className="text-xl font-bold tabular-nums block mt-1"
                    style={{ color: optKpis.avg_delivery_success_rate >= 90 ? 'var(--color-success)' : 'var(--color-warning)', fontFamily: 'var(--kpi-font)' }}
                  />
                ) : (
                  <p className="text-sm mt-2" style={{ color: 'var(--color-text-muted)' }}>Sin datos</p>
                )}
              </div>

              {/* Avg savings */}
              <div className="theme-card animate-fade-in-up" style={{ padding: 'var(--card-padding)', animationDelay: '480ms' }}>
                <p className="text-xs font-medium" style={{ color: 'var(--color-text-secondary)' }}>Ahorro promedio</p>
                {optKpis.avg_savings_percent !== null ? (
                  <AnimatedCounter
                    value={Math.round(optKpis.avg_savings_percent)}
                    formatter={(n) => `${n}%`}
                    className="text-xl font-bold tabular-nums block mt-1"
                    style={{ color: 'var(--color-accent)', fontFamily: 'var(--kpi-font)' }}
                  />
                ) : (
                  <p className="text-sm mt-2" style={{ color: 'var(--color-text-muted)' }}>Sin datos</p>
                )}
              </div>
            </div>
          </section>
        )}

        {/* ── Quick Links ── */}
        <section className="grid grid-cols-1 sm:grid-cols-3 gap-3" style={{ gap: 'var(--section-gap, 0.75rem)' }}>
          <a
            href="/customer/routes"
            className="theme-card flex items-center gap-3 transition-all hover:scale-[1.01] animate-fade-in-up"
            style={{ padding: 'var(--card-padding)', animationDelay: '540ms' }}
          >
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg" style={{ backgroundColor: 'var(--color-accent-muted)', color: 'var(--color-accent)' }}>
              <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z" />
              </svg>
            </div>
            <div>
              <p className="text-sm font-medium" style={{ color: 'var(--color-text-primary)' }}>Ver rutas</p>
              <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>Historial de rutas y entregas</p>
            </div>
          </a>

          <a
            href="/customer/shipments"
            className="theme-card flex items-center gap-3 transition-all hover:scale-[1.01] animate-fade-in-up"
            style={{ padding: 'var(--card-padding)', animationDelay: '600ms' }}
          >
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-500/10 text-blue-500">
              <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
              </svg>
            </div>
            <div>
              <p className="text-sm font-medium" style={{ color: 'var(--color-text-primary)' }}>Ver envios</p>
              <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>Seguimiento de paquetes</p>
            </div>
          </a>

          <a
            href="/app/admin/fleet-map"
            className="theme-card flex items-center gap-3 transition-all hover:scale-[1.01] animate-fade-in-up"
            style={{ padding: 'var(--card-padding)', animationDelay: '660ms' }}
          >
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-500">
              <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
              </svg>
            </div>
            <div>
              <p className="text-sm font-medium" style={{ color: 'var(--color-text-primary)' }}>Mapa en vivo</p>
              <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>Posiciones en tiempo real</p>
            </div>
          </a>
        </section>
      </div>
    </div>
  );
}
