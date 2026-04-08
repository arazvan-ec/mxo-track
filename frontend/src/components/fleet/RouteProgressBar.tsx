import type { FleetRoute } from '@/api/types';

interface Props {
  route: FleetRoute;
}

export function RouteProgressBar({ route }: Props) {
  const pct =
    route.totalStops > 0
      ? Math.round((route.deliveredStops / route.totalStops) * 100)
      : 0;

  return (
    <div className="px-4 py-3 border-t" style={{ borderColor: 'var(--color-border-subtle)' }}>
      <div className="flex items-center justify-between mb-1.5">
        <span className="text-xs font-medium truncate" style={{ color: 'var(--color-text-primary)' }}>
          {route.name}
        </span>
        <span className="text-xs" style={{ color: 'var(--color-text-secondary)' }}>
          {route.deliveredStops}/{route.totalStops} stops
        </span>
      </div>
      <div className="w-full h-2 rounded-full overflow-hidden" style={{ backgroundColor: 'var(--color-border)' }}>
        <div
          className="h-full bg-emerald-500 rounded-full transition-all duration-500"
          style={{ width: `${pct}%` }}
        />
      </div>
    </div>
  );
}
