import { useQuery } from '@tanstack/react-query';
import { api } from '@/api/client';
import type { WidgetProps } from './types';

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

function SimpleBarChart({ data }: { data: DailyDelivery[] }) {
  if (data.length === 0) return <p className="text-sm text-center py-4" style={{ color: 'var(--color-text-muted)' }}>Sin datos</p>;

  const max = Math.max(...data.map((d) => d.deliveries), 1);

  return (
    <div className="flex items-end gap-2 h-36">
      {data.map((d) => (
        <div key={d.date} className="flex-1 flex flex-col items-center gap-1">
          <span className="text-xs tabular-nums" style={{ color: 'var(--color-text-secondary)' }}>{d.deliveries}</span>
          <div
            className="w-full rounded-t transition-all duration-300 min-h-[2px]"
            style={{ height: `${(d.deliveries / max) * 100}%`, backgroundColor: 'var(--color-accent)' }}
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
    return <p className="text-sm text-center py-4" style={{ color: 'var(--color-text-muted)' }}>Sin datos de entregas esta semana.</p>;
  }

  const medalColors = ['bg-amber-100 text-amber-700', 'bg-gray-100 text-gray-600', 'bg-orange-100 text-orange-700'];

  return (
    <div>
      {drivers.map((driver, i) => (
        <div
          key={driver.driver_email}
          className="flex items-center gap-4 py-3 border-b last:border-b-0"
          style={{ borderColor: 'var(--color-border)' }}
        >
          <span className={`inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold ${medalColors[i] ?? 'bg-gray-50 text-gray-500'}`}>
            {i + 1}
          </span>
          <div className="min-w-0 flex-1">
            <p className="text-sm font-medium truncate" style={{ color: 'var(--color-text-primary)' }}>{driver.driver_name}</p>
            <p className="text-xs truncate" style={{ color: 'var(--color-text-secondary)' }}>{driver.driver_email}</p>
          </div>
          <span className="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" style={{ backgroundColor: 'rgba(16,185,129,0.1)', color: 'var(--color-success)' }}>
            {driver.deliveries} entregas
          </span>
        </div>
      ))}
    </div>
  );
}

export function MiniReportsWidget({ data: _data }: WidgetProps) {
  const { data: reports } = useQuery({
    queryKey: ['dashboard-reports'],
    queryFn: () => api.get<ReportsResponse>('/api/admin/dashboard-reports'),
    staleTime: 60 * 1000,
    refetchInterval: 30 * 1000,
  });

  return (
    <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
      {/* Delivery trend chart */}
      <div
        className="rounded-xl p-6 shadow-sm ring-1"
        style={{ backgroundColor: 'var(--color-surface-elevated)', borderColor: 'var(--color-border)' }}
      >
        <h3 className="text-base font-semibold mb-4" style={{ color: 'var(--color-text-primary)' }}>Entregas (últimos 7 días)</h3>
        <SimpleBarChart data={reports?.daily_deliveries ?? []} />
      </div>

      {/* Top drivers */}
      <div
        className="rounded-xl shadow-sm ring-1 overflow-hidden"
        style={{ backgroundColor: 'var(--color-surface-elevated)', borderColor: 'var(--color-border)' }}
      >
        <div className="px-6 py-4 border-b" style={{ borderColor: 'var(--color-border)' }}>
          <h3 className="text-base font-semibold" style={{ color: 'var(--color-text-primary)' }}>Top transportistas (esta semana)</h3>
        </div>
        <div className="px-6">
          <TopDriversList drivers={reports?.top_drivers ?? []} />
        </div>
      </div>
    </div>
  );
}
