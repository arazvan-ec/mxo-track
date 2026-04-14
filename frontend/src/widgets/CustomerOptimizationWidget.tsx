import { useCustomerOptimizationKpis } from '@/api/hooks/useCustomerDashboard';
import { AnimatedCounter } from '@/components/ui/AnimatedCounter';
import type { WidgetProps } from './types';

function formatMinutes(minutes: number): string {
  if (minutes < 60) return `${minutes}m`;
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  return m > 0 ? `${h}h ${m}m` : `${h}h`;
}

export function CustomerOptimizationSummary({ data: _data }: WidgetProps) {
  const { data: kpis } = useCustomerOptimizationKpis();
  if (!kpis) return null;
  const km = Math.round(kpis.monthly_km_saved);
  return (
    <span className="text-xs tabular-nums" style={{ color: 'var(--color-text-secondary)' }}>
      <span className="font-semibold" style={{ color: 'var(--color-success)' }}>
        {km.toLocaleString('es-ES')} km
      </span>{' '}ahorrados
    </span>
  );
}

export function CustomerOptimizationWidget({ data: _data }: WidgetProps) {
  const { data: kpis } = useCustomerOptimizationKpis();
  if (!kpis) return null;

  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3" style={{ gap: 'var(--section-gap, 0.75rem)' }}>
      {/* Monthly km saved */}
      <div className="theme-card animate-fade-in-up" style={{ padding: 'var(--card-padding)' }}>
        <p className="text-xs font-medium" style={{ color: 'var(--color-text-secondary)' }}>Km ahorrados (mes)</p>
        <AnimatedCounter
          value={Math.round(kpis.monthly_km_saved)}
          formatter={(n) => `${n} km`}
          className="text-xl font-bold tabular-nums block mt-1"
          style={{ color: 'var(--color-success)', fontFamily: 'var(--kpi-font)' }}
        />
        <p className="text-xs mt-1" style={{ color: 'var(--color-text-muted)' }}>
          Total: {Math.round(kpis.total_km_saved).toLocaleString('es-ES')} km
        </p>
      </div>

      {/* Time saved */}
      <div className="theme-card animate-fade-in-up" style={{ padding: 'var(--card-padding)', animationDelay: '60ms' }}>
        <p className="text-xs font-medium" style={{ color: 'var(--color-text-secondary)' }}>Tiempo ahorrado (mes)</p>
        <p className="text-xl font-bold tabular-nums mt-1" style={{ color: 'var(--color-accent)', fontFamily: 'var(--kpi-font)' }}>
          {formatMinutes(kpis.monthly_time_saved_minutes)}
        </p>
        <p className="text-xs mt-1" style={{ color: 'var(--color-text-muted)' }}>
          Total: {formatMinutes(kpis.total_time_saved_minutes)}
        </p>
      </div>

      {/* Delivery success rate */}
      <div className="theme-card animate-fade-in-up" style={{ padding: 'var(--card-padding)', animationDelay: '120ms' }}>
        <p className="text-xs font-medium" style={{ color: 'var(--color-text-secondary)' }}>Tasa de exito</p>
        {kpis.avg_delivery_success_rate !== null ? (
          <>
            <AnimatedCounter
              value={Math.round(kpis.avg_delivery_success_rate)}
              formatter={(n) => `${n}%`}
              className="text-xl font-bold tabular-nums block mt-1"
              style={{ color: kpis.avg_delivery_success_rate >= 90 ? 'var(--color-success)' : 'var(--color-warning)', fontFamily: 'var(--kpi-font)' }}
            />
            <p className="text-xs mt-1" style={{ color: 'var(--color-text-muted)' }}>
              {kpis.routes_with_metrics} rutas con metricas
            </p>
          </>
        ) : (
          <p className="text-sm mt-2" style={{ color: 'var(--color-text-muted)' }}>Sin datos</p>
        )}
      </div>

      {/* Average savings */}
      <div className="theme-card animate-fade-in-up" style={{ padding: 'var(--card-padding)', animationDelay: '180ms' }}>
        <p className="text-xs font-medium" style={{ color: 'var(--color-text-secondary)' }}>Ahorro promedio</p>
        {kpis.avg_savings_percent !== null ? (
          <AnimatedCounter
            value={Math.round(kpis.avg_savings_percent)}
            formatter={(n) => `${n}%`}
            className="text-xl font-bold tabular-nums block mt-1"
            style={{ color: 'var(--color-accent)', fontFamily: 'var(--kpi-font)' }}
          />
        ) : (
          <p className="text-sm mt-2" style={{ color: 'var(--color-text-muted)' }}>Sin datos</p>
        )}
      </div>
    </div>
  );
}
