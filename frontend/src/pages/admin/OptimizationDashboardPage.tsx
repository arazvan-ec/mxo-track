import { useOptimizerMetrics, useAddressRisks, useReoptHistory } from '@/api/hooks/useOptimizationAnalytics';

/* ── Loading spinner ─────────────────────────────────────────────── */

function LoadingSpinner() {
  return (
    <div className="flex items-center justify-center h-32">
      <div
        className="animate-spin h-6 w-6 border-2 rounded-full border-t-transparent"
        style={{ borderColor: 'var(--color-accent)', borderTopColor: 'transparent' }}
      />
      <span className="ml-3 text-sm" style={{ color: 'var(--color-text-secondary)' }}>Cargando...</span>
    </div>
  );
}

/* ── Card wrapper ────────────────────────────────────────────────── */

function Card({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div
      className="rounded-lg border p-5"
      style={{ backgroundColor: 'var(--color-surface-elevated)', borderColor: 'var(--color-border)' }}
    >
      <h2 className="text-lg font-semibold mb-4" style={{ color: 'var(--color-text-primary)' }}>
        {title}
      </h2>
      {children}
    </div>
  );
}

/* ── Optimizer Performance ───────────────────────────────────────── */

function OptimizerPerformanceCard() {
  const { data, isLoading } = useOptimizerMetrics();

  if (isLoading) return <Card title="Rendimiento de Optimizadores"><LoadingSpinner /></Card>;

  if (!data || data.length === 0) {
    return (
      <Card title="Rendimiento de Optimizadores">
        <p className="text-sm" style={{ color: 'var(--color-text-muted)' }}>
          No hay metricas de optimizadores disponibles.
        </p>
      </Card>
    );
  }

  return (
    <Card title="Rendimiento de Optimizadores">
      <div className="overflow-x-auto">
        <table className="min-w-full text-sm">
          <thead>
            <tr className="border-b" style={{ borderColor: 'var(--color-border)' }}>
              <th className="text-left py-2 px-3 font-medium" style={{ color: 'var(--color-text-secondary)' }}>Optimizador</th>
              <th className="text-right py-2 px-3 font-medium" style={{ color: 'var(--color-text-secondary)' }}>Dist. Prom. (km)</th>
              <th className="text-right py-2 px-3 font-medium" style={{ color: 'var(--color-text-secondary)' }}>Duracion Prom. (min)</th>
              <th className="text-right py-2 px-3 font-medium" style={{ color: 'var(--color-text-secondary)' }}>Rutas</th>
              <th className="text-right py-2 px-3 font-medium" style={{ color: 'var(--color-text-secondary)' }}>Tasa Exito</th>
            </tr>
          </thead>
          <tbody>
            {data.map((m) => (
              <tr key={m.optimizer_name} className="border-b" style={{ borderColor: 'var(--color-border)' }}>
                <td className="py-2 px-3 font-medium" style={{ color: 'var(--color-text-primary)' }}>{m.optimizer_name}</td>
                <td className="py-2 px-3 text-right" style={{ color: 'var(--color-text-secondary)' }}>{m.avg_distance_km.toFixed(1)}</td>
                <td className="py-2 px-3 text-right" style={{ color: 'var(--color-text-secondary)' }}>{m.avg_duration_min.toFixed(0)}</td>
                <td className="py-2 px-3 text-right" style={{ color: 'var(--color-text-secondary)' }}>{m.route_count}</td>
                <td className="py-2 px-3 text-right">
                  <span
                    className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${
                      m.avg_success_rate >= 0.9 ? 'badge-green' : m.avg_success_rate >= 0.7 ? 'badge-yellow' : 'badge-red'
                    }`}
                  >
                    {(m.avg_success_rate * 100).toFixed(0)}%
                  </span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </Card>
  );
}

/* ── High-Risk Addresses ─────────────────────────────────────────── */

function AddressRisksCard() {
  const { data, isLoading } = useAddressRisks();

  if (isLoading) return <Card title="Direcciones de Alto Riesgo"><LoadingSpinner /></Card>;

  if (!data || data.length === 0) {
    return (
      <Card title="Direcciones de Alto Riesgo">
        <p className="text-sm" style={{ color: 'var(--color-text-muted)' }}>
          No hay direcciones de riesgo identificadas.
        </p>
      </Card>
    );
  }

  return (
    <Card title="Direcciones de Alto Riesgo">
      <div className="overflow-x-auto">
        <table className="min-w-full text-sm">
          <thead>
            <tr className="border-b" style={{ borderColor: 'var(--color-border)' }}>
              <th className="text-left py-2 px-3 font-medium" style={{ color: 'var(--color-text-secondary)' }}>Direccion</th>
              <th className="text-right py-2 px-3 font-medium" style={{ color: 'var(--color-text-secondary)' }}>Entregas</th>
              <th className="text-right py-2 px-3 font-medium" style={{ color: 'var(--color-text-secondary)' }}>Excepciones</th>
              <th className="text-right py-2 px-3 font-medium" style={{ color: 'var(--color-text-secondary)' }}>Tasa Excepcion</th>
              <th className="text-center py-2 px-3 font-medium" style={{ color: 'var(--color-text-secondary)' }}>Riesgo</th>
            </tr>
          </thead>
          <tbody>
            {data.map((a) => (
              <tr key={a.address} className="border-b" style={{ borderColor: 'var(--color-border)' }}>
                <td className="py-2 px-3 max-w-xs truncate" style={{ color: 'var(--color-text-primary)' }} title={a.address}>{a.address}</td>
                <td className="py-2 px-3 text-right" style={{ color: 'var(--color-text-secondary)' }}>{a.total_deliveries}</td>
                <td className="py-2 px-3 text-right" style={{ color: 'var(--color-text-secondary)' }}>{a.exception_count}</td>
                <td className="py-2 px-3 text-right" style={{ color: 'var(--color-text-secondary)' }}>{(a.exception_rate * 100).toFixed(1)}%</td>
                <td className="py-2 px-3 text-center">
                  {a.is_high_risk ? (
                    <span className="inline-flex rounded-full badge-red px-2 text-xs font-semibold leading-5">Alto</span>
                  ) : (
                    <span className="inline-flex rounded-full badge-green px-2 text-xs font-semibold leading-5">Normal</span>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </Card>
  );
}

/* ── Re-optimization History ─────────────────────────────────────── */

function ReoptHistoryCard() {
  const { data, isLoading } = useReoptHistory();

  if (isLoading) return <Card title="Historial de Re-optimizacion"><LoadingSpinner /></Card>;

  if (!data || data.length === 0) {
    return (
      <Card title="Historial de Re-optimizacion">
        <p className="text-sm" style={{ color: 'var(--color-text-muted)' }}>
          No hay eventos de re-optimizacion recientes.
        </p>
      </Card>
    );
  }

  const triggerLabel = (trigger: string) => {
    switch (trigger) {
      case 'on_exception': return 'Excepcion';
      case 'on_skip': return 'Salto';
      case 'on_delay': return 'Retraso';
      case 'manual': return 'Manual';
      default: return trigger;
    }
  };

  const formatDate = (iso: string) => {
    const d = new Date(iso);
    return d.toLocaleDateString('es', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  };

  return (
    <Card title="Historial de Re-optimizacion">
      <div className="overflow-x-auto">
        <table className="min-w-full text-sm">
          <thead>
            <tr className="border-b" style={{ borderColor: 'var(--color-border)' }}>
              <th className="text-left py-2 px-3 font-medium" style={{ color: 'var(--color-text-secondary)' }}>Ruta</th>
              <th className="text-left py-2 px-3 font-medium" style={{ color: 'var(--color-text-secondary)' }}>Disparador</th>
              <th className="text-left py-2 px-3 font-medium" style={{ color: 'var(--color-text-secondary)' }}>Fecha</th>
            </tr>
          </thead>
          <tbody>
            {data.map((e, i) => (
              <tr key={`${e.route_public_id}-${i}`} className="border-b" style={{ borderColor: 'var(--color-border)' }}>
                <td className="py-2 px-3 font-mono text-xs" style={{ color: 'var(--color-text-primary)' }}>
                  {e.route_public_id.slice(0, 8)}...
                </td>
                <td className="py-2 px-3" style={{ color: 'var(--color-text-secondary)' }}>{triggerLabel(e.trigger)}</td>
                <td className="py-2 px-3" style={{ color: 'var(--color-text-secondary)' }}>{formatDate(e.occurred_at)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </Card>
  );
}

/* ── Main Page ───────────────────────────────────────────────────── */

export function OptimizationDashboardPage() {
  return (
    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
      <div className="mb-6">
        <h1 className="text-2xl font-bold" style={{ color: 'var(--color-text-primary)' }}>
          Analytics de Optimizacion
        </h1>
        <p className="mt-1 text-sm" style={{ color: 'var(--color-text-secondary)' }}>
          Metricas de rendimiento de optimizadores, direcciones de riesgo y eventos de re-optimizacion.
        </p>
      </div>

      <div className="space-y-6">
        <OptimizerPerformanceCard />
        <AddressRisksCard />
        <ReoptHistoryCard />
      </div>
    </div>
  );
}
