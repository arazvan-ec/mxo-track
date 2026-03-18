import { SKILL_COLORS, DEFAULT_MARKER_COLOR } from '@/components/maps/shared/colors';
import type { FleetVehicle } from '@/api/types';

interface Props {
  vehicles: FleetVehicle[];
  searchQuery: string;
  selectedId: string | null;
  onSelect: (vehicle: FleetVehicle) => void;
}

export function VehicleList({ vehicles, searchQuery, selectedId, onSelect }: Props) {
  const filtered = searchQuery.trim()
    ? vehicles.filter((v) =>
        v.name.toLowerCase().includes(searchQuery.toLowerCase()),
      )
    : vehicles;

  if (filtered.length === 0) {
    return (
      <div className="text-center py-8 text-slate-600 text-sm">
        No vehicles found
      </div>
    );
  }

  return (
    <div className="space-y-1.5">
      {filtered.map((v) => (
        <button
          key={v.public_id}
          onClick={() => onSelect(v)}
          className={`w-full text-left p-3 rounded-lg transition-all border ${
            selectedId === v.public_id
              ? 'bg-blue-600/20 border-blue-500/40 shadow-lg shadow-blue-500/10'
              : 'bg-slate-800/50 border-slate-700/30 hover:bg-slate-800/80 hover:border-slate-600/50'
          }`}
        >
          <div className="flex items-center justify-between mb-1">
            <div className="flex items-center gap-2">
              <span className="relative flex h-2 w-2">
                {v.last_position && (
                  <span className="absolute inline-flex h-full w-full rounded-full opacity-75 bg-emerald-400 animate-ping" />
                )}
                <span
                  className={`relative inline-flex rounded-full h-2 w-2 ${
                    v.last_position ? 'bg-emerald-500' : 'bg-slate-600'
                  }`}
                />
              </span>
              <span className="text-sm font-medium text-slate-200 truncate">
                {v.name}
              </span>
            </div>
            {v.last_position?.device_time && (
              <span className="text-[10px] text-slate-500">
                {v.last_position.device_time}
              </span>
            )}
          </div>

          <div className="flex items-center gap-3 text-[11px]">
            {v.last_position ? (
              <span className="text-slate-400">
                {Math.round(v.last_position.speed ?? 0)} km/h
              </span>
            ) : (
              <span className="text-slate-600">No signal</span>
            )}
            {v.route_name && (
              <span className="text-slate-500 truncate">{v.route_name}</span>
            )}
          </div>

          {v.skills && v.skills.length > 0 && (
            <div className="flex flex-wrap gap-1 mt-1.5">
              {v.skills.map((skill) => {
                const color = SKILL_COLORS[skill] ?? DEFAULT_MARKER_COLOR;
                return (
                  <span
                    key={skill}
                    className="inline-block text-[8px] font-semibold px-1 py-px rounded uppercase tracking-wide"
                    style={{
                      background: `${color}20`,
                      color,
                    }}
                  >
                    {skill}
                  </span>
                );
              })}
            </div>
          )}
        </button>
      ))}
    </div>
  );
}
