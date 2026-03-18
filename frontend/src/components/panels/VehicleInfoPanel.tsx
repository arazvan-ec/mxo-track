import { SKILL_COLORS, DEFAULT_MARKER_COLOR } from '@/components/maps/shared/colors';

interface VehicleInfo {
  name: string;
  driverName?: string;
  speed?: number;
  deviceTime?: string;
  skills?: string[];
}

interface Props {
  vehicle: VehicleInfo | null;
}

/**
 * Vehicle information panel — shows driver, speed, skills.
 * Used in route detail views when a vehicle is assigned.
 */
export function VehicleInfoPanel({ vehicle }: Props) {
  if (!vehicle) return null;

  return (
    <div className="bg-slate-800/60 rounded-lg p-3 border border-slate-700/40">
      <div className="text-[10px] text-slate-500 uppercase tracking-wider mb-2">Vehicle</div>

      <div className="text-sm font-medium text-white mb-1">{vehicle.name}</div>

      {vehicle.driverName && (
        <div className="text-xs text-slate-400 mb-1">
          Driver: {vehicle.driverName}
        </div>
      )}

      <div className="flex items-center gap-3 text-xs text-slate-400">
        {vehicle.speed != null && (
          <span>{Math.round(vehicle.speed)} km/h</span>
        )}
        {vehicle.deviceTime && (
          <span className="text-slate-500">{vehicle.deviceTime}</span>
        )}
      </div>

      {vehicle.skills && vehicle.skills.length > 0 && (
        <div className="flex flex-wrap gap-1 mt-2">
          {vehicle.skills.map((skill) => {
            const color = SKILL_COLORS[skill] ?? DEFAULT_MARKER_COLOR;
            return (
              <span
                key={skill}
                className="text-[8px] font-semibold px-1 py-px rounded uppercase"
                style={{ background: `${color}20`, color }}
              >
                {skill}
              </span>
            );
          })}
        </div>
      )}
    </div>
  );
}
