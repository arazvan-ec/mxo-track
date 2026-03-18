import type { FleetRoute } from '@/api/types';

interface Props {
  route: FleetRoute;
}

export function RouteProgressBar({ route }: Props) {
  const pct =
    route.total_stops > 0
      ? Math.round((route.delivered_stops / route.total_stops) * 100)
      : 0;

  return (
    <div className="px-4 py-3 border-t border-slate-700/50">
      <div className="flex items-center justify-between mb-1.5">
        <span className="text-xs font-medium text-slate-300 truncate">
          {route.name}
        </span>
        <span className="text-xs text-slate-400">
          {route.delivered_stops}/{route.total_stops} stops
        </span>
      </div>
      <div className="w-full h-2 bg-slate-800 rounded-full overflow-hidden">
        <div
          className="h-full bg-emerald-500 rounded-full transition-all duration-500"
          style={{ width: `${pct}%` }}
        />
      </div>
    </div>
  );
}
