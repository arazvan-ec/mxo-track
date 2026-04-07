import { useQuery } from '@tanstack/react-query';
import { api } from '@/api/client';
import type { WidgetProps } from './types';
import { AnimatedCounter } from '@/components/ui/AnimatedCounter';

interface DailyDelivery {
  date: string;
  deliveries: number;
}

interface TopDriver {
  driver_name: string;
  driver_email: string;
  deliveries: number;
}

interface ReportsResponse {
  daily_deliveries: DailyDelivery[];
  top_drivers: TopDriver[];
}

function AnimatedBarChart({ data }: { data: DailyDelivery[] }) {
  if (data.length === 0) return <p className="text-sm text-center py-4" style={{ color: 'var(--color-text-muted)' }}>Sin datos</p>;

  const max = Math.max(...data.map((d) => d.deliveries), 1);

  return (
    <div className="flex items-end gap-2 h-36">
      {data.map((d, i) => (
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
              height: `${(d.deliveries / max) * 100}%`,
              minHeight: 2,
              background: `linear-gradient(to top, var(--color-accent), var(--color-accent-hover))`,
              animationDelay: `${i * 80}ms`,
            }}
          />
          <span className="text-[10px] truncate w-full text-center" style={{ color: 'var(--color-text-muted)' }}>
            {d.date.slice(5)}
          </span>
        </div>
      ))}
    </div>
  );
}

function TopDriversList({ drivers }: { drivers: TopDriver[] }) {
  if (drivers.length === 0) {
    return <p className="text-sm text-center py-4" style={{ color: 'var(--color-text-muted)' }}>Sin datos esta semana.</p>;
  }

  const medalColors = ['var(--color-warning)', '#94a3b8', '#f97316'];

  return (
    <div className="space-y-1">
      {drivers.map((driver, i) => (
        <div
          key={driver.driver_email}
          className="flex items-center gap-3 py-2 px-2 rounded-lg transition-colors"
          style={{ backgroundColor: i === 0 ? 'var(--color-accent-muted)' : 'transparent' }}
        >
          <span
            className="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold"
            style={{
              backgroundColor: medalColors[i] ? `${medalColors[i]}20` : 'var(--color-accent-muted)',
              color: medalColors[i] ?? 'var(--color-text-secondary)',
            }}
          >
            {i + 1}
          </span>
          <div className="min-w-0 flex-1">
            <p className="text-sm font-medium truncate" style={{ color: 'var(--color-text-primary)' }}>
              {driver.driver_name}
            </p>
          </div>
          <span className="text-xs font-bold tabular-nums" style={{ color: 'var(--color-accent)', fontFamily: 'var(--data-font)' }}>
            <AnimatedCounter value={driver.deliveries} />
          </span>
        </div>
      ))}
    </div>
  );
}

// eslint-disable-next-line @typescript-eslint/no-unused-vars
export function MiniReportsWidget({ data }: WidgetProps) {
  const { data: reports } = useQuery({
    queryKey: ['dashboard-reports'],
    queryFn: () => api.get<ReportsResponse>('/api/admin/dashboard-reports'),
    staleTime: 60 * 1000,
    refetchInterval: 30 * 1000,
  });

  return (
    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2" style={{ gap: 'var(--section-gap, 1rem)' }}>
      {/* Delivery trend chart */}
      <div className="theme-card animate-fade-in-up" style={{ padding: 'var(--card-padding)' }}>
        <h3 className="text-sm font-semibold mb-4" style={{ color: 'var(--color-text-primary)' }}>
          Entregas (ultimos 7 dias)
        </h3>
        <AnimatedBarChart data={reports?.daily_deliveries ?? []} />
      </div>

      {/* Top drivers */}
      <div className="theme-card animate-fade-in-up overflow-hidden" style={{ animationDelay: '60ms' }}>
        <div className="px-4 py-3 border-b" style={{ borderColor: 'var(--color-border)', padding: 'var(--card-padding)' }}>
          <h3 className="text-sm font-semibold" style={{ color: 'var(--color-text-primary)' }}>
            Top transportistas
          </h3>
        </div>
        <div style={{ padding: 'var(--card-padding)' }}>
          <TopDriversList drivers={reports?.top_drivers ?? []} />
        </div>
      </div>
    </div>
  );
}
