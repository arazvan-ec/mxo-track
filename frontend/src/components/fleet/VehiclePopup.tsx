import { SKILL_COLORS } from '@/components/maps/shared/colors';
import type { FleetVehicle } from '@/api/types';

interface Props {
  vehicle: FleetVehicle;
  routeName?: string;
}

export function VehiclePopup({ vehicle, routeName }: Props) {
  const speed = vehicle.last_position
    ? Math.round(vehicle.last_position.speed ?? 0)
    : 0;
  const time = vehicle.last_position?.device_time ?? '--:--';

  return (
    <div className="min-w-[180px] text-xs leading-relaxed">
      <div className="font-bold text-sm mb-1">{vehicle.name}</div>
      <div className="text-slate-400 mb-1.5">
        {speed} km/h &middot; {time}
      </div>
      <div className="flex gap-1.5 mb-0.5">
        <span className="text-slate-500 w-16">Ruta:</span>
        <span className="text-slate-200">{routeName ?? 'Sin ruta'}</span>
      </div>
      <div className="flex gap-1.5 mb-0.5">
        <span className="text-slate-500 w-16">Conductor:</span>
        <span className="text-slate-200">{vehicle.driver_name ?? 'Sin conductor'}</span>
      </div>
      {vehicle.skills && vehicle.skills.length > 0 && (
        <div className="flex flex-wrap gap-1 mt-1.5">
          {vehicle.skills.map((skill) => {
            const color = SKILL_COLORS[skill] ?? '#6366f1';
            return (
              <span
                key={skill}
                className="text-[9px] font-semibold px-1.5 py-px rounded"
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
