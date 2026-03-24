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

const cardStyle = {
  backgroundColor: 'color-mix(in srgb, var(--color-surface-elevated) 60%, transparent)',
  borderColor: 'var(--color-border-subtle)',
};

/**
 * Vehicle information panel — shows driver, speed, skills.
 * Used in route detail views when a vehicle is assigned.
 */
export function VehicleInfoPanel({ vehicle }: Props) {
  if (!vehicle) return null;

  return (
    <div className="rounded-lg p-3 border" style={cardStyle}>
      <div className="text-[10px] uppercase tracking-wider mb-2" style={{ color: 'var(--color-text-muted)' }}>Vehicle</div>

      <div className="text-sm font-medium mb-1" style={{ color: 'var(--color-text-primary)' }}>{vehicle.name}</div>

      {vehicle.driverName && (
        <div className="text-xs mb-1" style={{ color: 'var(--color-text-secondary)' }}>
          Driver: {vehicle.driverName}
        </div>
      )}

      <div className="flex items-center gap-3 text-xs" style={{ color: 'var(--color-text-secondary)' }}>
        {vehicle.speed != null && (
          <span>{Math.round(vehicle.speed)} km/h</span>
        )}
        {vehicle.deviceTime && (
          <span style={{ color: 'var(--color-text-muted)' }}>{vehicle.deviceTime}</span>
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
