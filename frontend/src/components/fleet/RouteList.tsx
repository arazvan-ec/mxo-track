import type { FleetRoute } from '@/api/types';

interface Props {
  routes: FleetRoute[];
  selectedId: string | null;
  onSelect: (route: FleetRoute) => void;
}

export function RouteList({ routes, selectedId, onSelect }: Props) {
  if (routes.length === 0) {
    return (
      <div className="text-center py-8 text-slate-600 text-sm">
        No active routes
      </div>
    );
  }

  return (
    <div className="space-y-1.5">
      {routes.map((r) => (
        <button
          key={r.publicId}
          onClick={() => onSelect(r)}
          className={`w-full text-left p-3 rounded-lg transition-all border ${
            selectedId === r.publicId
              ? 'bg-blue-600/20 border-blue-500/40 shadow-lg shadow-blue-500/10'
              : 'bg-slate-800/50 border-slate-700/30 hover:bg-slate-800/80 hover:border-slate-600/50'
          }`}
        >
          <div className="flex items-center justify-between mb-1.5">
            <span className="text-sm font-medium text-slate-200 truncate">
              {r.name}
            </span>
            <span
              className={`text-[10px] font-medium px-1.5 py-0.5 rounded-full ${
                r.status === 'ACTIVE'
                  ? 'bg-emerald-500/20 text-emerald-400'
                  : 'bg-blue-500/20 text-blue-400'
              }`}
            >
              {r.status}
            </span>
          </div>
          <div className="flex items-center justify-between text-[11px]">
            <span className="text-slate-500">
              {r.vehicleName ?? 'No vehicle'}
            </span>
            <div className="flex items-center gap-1.5">
              <span className="text-emerald-400">{r.deliveredStops}</span>
              <span className="text-slate-600">/</span>
              <span className="text-slate-400">{r.totalStops}</span>
              <span className="text-slate-600 text-[10px]">stops</span>
            </div>
          </div>
          {r.driverName && (
            <div className="mt-1 text-[10px] text-slate-500 truncate">
              {r.driverName}
            </div>
          )}
        </button>
      ))}
    </div>
  );
}
