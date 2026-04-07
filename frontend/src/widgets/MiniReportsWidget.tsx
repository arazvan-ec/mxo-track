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
  if (data.length === 0) return <p className="text-sm text-gray-400 text-center py-4">Sin datos</p>;

  const max = Math.max(...data.map((d) => d.deliveries), 1);

  return (
    <div className="flex items-end gap-2 h-36">
      {data.map((d) => (
        <div key={d.date} className="flex-1 flex flex-col items-center gap-1">
          <span className="text-xs tabular-nums text-gray-500">{d.deliveries}</span>
          <div
            className="w-full rounded-t bg-emerald-400 transition-all duration-300 min-h-[2px]"
            style={{ height: `${(d.deliveries / max) * 100}%` }}
          />
          <span className="text-[10px] text-gray-400 truncate w-full text-center">
            {d.date.slice(5)}
          </span>
        </div>
      ))}
    </div>
  );
}

function TopDriversList({ drivers }: { drivers: TopDriver[] }) {
  if (drivers.length === 0) {
    return <p className="text-sm text-gray-400 text-center py-4">Sin datos de entregas esta semana.</p>;
  }

  const medalColors = ['bg-amber-100 text-amber-700', 'bg-gray-100 text-gray-600', 'bg-orange-100 text-orange-700'];

  return (
    <div className="divide-y divide-gray-100">
      {drivers.map((driver, i) => (
        <div key={driver.driver_email} className="flex items-center gap-4 py-3">
          <span className={`inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold ${medalColors[i] ?? 'bg-gray-50 text-gray-500'}`}>
            {i + 1}
          </span>
          <div className="min-w-0 flex-1">
            <p className="text-sm font-medium text-gray-900 truncate">{driver.driver_name}</p>
            <p className="text-xs text-gray-500 truncate">{driver.driver_email}</p>
          </div>
          <span className="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">
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
      <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-900/5">
        <h3 className="text-base font-semibold text-gray-900 mb-4">Entregas (últimos 7 días)</h3>
        <SimpleBarChart data={reports?.daily_deliveries ?? []} />
      </div>

      {/* Top drivers */}
      <div className="rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5 overflow-hidden">
        <div className="px-6 py-4 border-b border-gray-100">
          <h3 className="text-base font-semibold text-gray-900">Top transportistas (esta semana)</h3>
        </div>
        <div className="px-6">
          <TopDriversList drivers={reports?.top_drivers ?? []} />
        </div>
      </div>
    </div>
  );
}
