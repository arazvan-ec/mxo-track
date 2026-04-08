import { useCustomerKpis } from '@/api/hooks/useCustomerDashboard';
import { AnimatedCounter } from '@/components/ui/AnimatedCounter';
import type { WidgetProps } from './types';

const KPI_CONFIG = [
  { key: 'total_shipments' as const, label: 'Envios totales', color: 'var(--color-accent)' },
  { key: 'active_routes' as const, label: 'Rutas activas', color: '#3b82f6' },
  { key: 'pending_deliveries' as const, label: 'Entregas pendientes', color: 'var(--color-warning)' },
  { key: 'completed_today' as const, label: 'Completadas hoy', color: 'var(--color-success)' },
  { key: 'exceptions' as const, label: 'Excepciones', color: 'var(--color-error)' },
];

// eslint-disable-next-line @typescript-eslint/no-unused-vars
export function CustomerKpisWidget({ data }: WidgetProps) {
  const { data: kpis } = useCustomerKpis();
  if (!kpis) return null;

  return (
    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3" style={{ gap: 'var(--section-gap, 0.75rem)' }}>
      {KPI_CONFIG.map((cfg, i) => (
        <div
          key={cfg.key}
          className="theme-card animate-fade-in-up"
          style={{ padding: 'var(--card-padding)', animationDelay: `${i * 60}ms` }}
        >
          <p className="text-xs font-medium" style={{ color: 'var(--color-text-secondary)' }}>{cfg.label}</p>
          <AnimatedCounter
            value={kpis[cfg.key]}
            className="text-2xl font-bold tabular-nums block mt-1"
            style={{ color: 'var(--color-text-primary)', fontFamily: 'var(--kpi-font)' }}
          />
          <div className="mt-2 h-0.5 rounded-full" style={{ background: `linear-gradient(to right, ${cfg.color}, transparent)` }} />
        </div>
      ))}
    </div>
  );
}
