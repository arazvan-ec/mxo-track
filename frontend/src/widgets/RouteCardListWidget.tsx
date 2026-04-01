import type { TestRoutingRoute, TestRoutingStop } from '@/api/hooks/useTestRoutingData';
import { ROUTE_COLORS } from '@/components/maps/shared/colors';
import type { FleetRoute, FleetStop } from '@/api/types';
import type { WidgetProps } from './types';

interface RouteCardListData {
  /** TestRouting data format */
  routesData?: TestRoutingRoute[];
  highlightedRouteIdx?: number | null;
  onRouteSelect?: (idx: number) => void;
  /** Fleet data format */
  routes?: FleetRoute[];
  selectedRouteId?: string | null;
  onSelectRoute?: (route: FleetRoute) => void;
  onStopClick?: (routePublicId: string, sequence: number) => void;
}

export function RouteCardListWidget({ data }: WidgetProps) {
  const { routesData, highlightedRouteIdx, onRouteSelect, routes, selectedRouteId, onSelectRoute, onStopClick } =
    data as RouteCardListData;

  // Fleet routes mode
  if (routes && routes.length > 0) {
    return (
      <div className="px-4 pb-4 space-y-2">
        {routes.map((route) => (
          <FleetRouteCard
            key={route.publicId}
            route={route}
            selected={selectedRouteId === route.publicId}
            onSelect={() => onSelectRoute?.(route)}
            onStopClick={onStopClick}
          />
        ))}
      </div>
    );
  }

  // TestRouting mode
  if (!routesData) return null;

  return (
    <div className="px-4 pb-4 space-y-3">
      {routesData.map((route, idx) => (
        <OptimizationRouteCard
          key={route.name}
          route={route}
          color={ROUTE_COLORS[idx % ROUTE_COLORS.length]}
          highlighted={highlightedRouteIdx === idx}
          onSelect={() => onRouteSelect?.(idx)}
        />
      ))}
    </div>
  );
}

const STOP_STATUS_STYLES: Record<string, { color: string; bg: string; label: string }> = {
  PENDING: { color: '#94A3B8', bg: '#94A3B820', label: 'Pending' },
  DELIVERED: { color: '#10B981', bg: '#10B98120', label: 'Delivered' },
  EXCEPTION: { color: '#EF4444', bg: '#EF444420', label: 'Exception' },
  SKIPPED: { color: '#F59E0B', bg: '#F59E0B20', label: 'Skipped' },
};

/** Fleet route card — shows name, status, vehicle, stop progress. Expands to show stops when selected. */
function FleetRouteCard({
  route,
  selected,
  onSelect,
  onStopClick,
}: {
  route: FleetRoute;
  selected: boolean;
  onSelect: () => void;
  onStopClick?: (routePublicId: string, sequence: number) => void;
}) {
  const STATUS_COLORS: Record<string, string> = {
    PLANNED: '#3B82F6',
    ACTIVE: '#F59E0B',
    DONE: '#10B981',
    CANCELLED: '#9CA3AF',
  };

  const statusColor = STATUS_COLORS[route.status] ?? '#6B7280';

  return (
    <div
      className={`w-full text-left rounded-lg overflow-hidden transition-all duration-200 border ${
        selected
          ? 'ring-2 ring-blue-500/60 shadow-lg shadow-blue-500/10 border-blue-500/40'
          : 'border-slate-700 hover:border-slate-600'
      }`}
      style={{ backgroundColor: 'var(--color-surface-elevated)' }}
    >
      <button type="button" onClick={onSelect} className="w-full text-left px-3 py-2.5">
        <div className="flex items-center justify-between mb-1">
          <div className="flex items-center gap-2 min-w-0">
            <div className="w-2.5 h-2.5 rounded-full flex-shrink-0" style={{ backgroundColor: route.color }} />
            <span className="text-sm font-semibold truncate" style={{ color: 'var(--color-text-primary)' }}>
              {route.name}
            </span>
          </div>
          <span
            className="text-[10px] font-semibold uppercase px-1.5 py-0.5 rounded flex-shrink-0"
            style={{ color: statusColor, backgroundColor: `${statusColor}20` }}
          >
            {route.status}
          </span>
        </div>
        <div className="flex items-center justify-between text-xs" style={{ color: 'var(--color-text-secondary)' }}>
          <span className="truncate">{route.vehicleName ?? 'No vehicle'}</span>
          <span className="flex-shrink-0">
            <span style={{ color: 'var(--color-success)' }}>{route.deliveredStops}</span>
            <span style={{ color: 'var(--color-text-muted)' }}> / {route.totalStops} stops</span>
          </span>
        </div>
        {route.driverName && (
          <div className="text-[11px] mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
            {route.driverName}
          </div>
        )}
      </button>

      {/* Expandable stop list */}
      {selected && route.stops.length > 0 && (
        <div className="border-t border-slate-700/50 max-h-60 overflow-y-auto">
          {route.stops.map((stop) => {
            const style = STOP_STATUS_STYLES[stop.status] ?? STOP_STATUS_STYLES.PENDING;
            return (
              <button
                key={stop.sequence}
                type="button"
                onClick={(e) => {
                  e.stopPropagation();
                  onStopClick?.(route.publicId, stop.sequence);
                }}
                className="w-full text-left px-3 py-1.5 flex items-center gap-2 hover:bg-slate-700/30 transition-colors"
              >
                <span
                  className="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold flex-shrink-0"
                  style={{ backgroundColor: route.color, color: '#fff' }}
                >
                  {stop.sequence}
                </span>
                <div className="flex-1 min-w-0">
                  <div className="text-xs truncate" style={{ color: 'var(--color-text-primary)' }}>
                    {stop.recipient ?? stop.address}
                  </div>
                  {stop.recipient && (
                    <div className="text-[10px] truncate" style={{ color: 'var(--color-text-muted)' }}>
                      {stop.address}
                    </div>
                  )}
                </div>
                <span
                  className="text-[9px] font-semibold uppercase px-1 py-0.5 rounded flex-shrink-0"
                  style={{ color: style.color, backgroundColor: style.bg }}
                >
                  {style.label}
                </span>
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
}

/** Optimization route card — shows before/after distance and stop order comparison */
function OptimizationRouteCard({
  route,
  color,
  highlighted,
  onSelect,
}: {
  route: TestRoutingRoute;
  color: string;
  highlighted: boolean;
  onSelect: () => void;
}) {
  return (
    <div
      className={`bg-slate-800 rounded-lg overflow-hidden transition-all duration-200 ${
        highlighted
          ? 'ring-2 ring-blue-500/60 shadow-lg shadow-blue-500/10'
          : 'ring-1 ring-slate-700'
      }`}
    >
      <button
        type="button"
        className="w-full px-3 py-2 border-b border-slate-700 flex items-center justify-between hover:bg-slate-700/50 transition-colors"
        onClick={onSelect}
      >
        <div className="flex items-center gap-2">
          <div className="w-3 h-3 rounded-full flex-shrink-0" style={{ backgroundColor: color }} />
          <h3 className="text-sm font-semibold text-slate-100">{route.name}</h3>
          <span className="text-xs text-slate-400">{route.vehicle}</span>
        </div>
        <span className="text-xs text-slate-400">{route.stopCount} stops</span>
      </button>

      <div className="grid grid-cols-4 gap-1 px-2 py-2">
        <MiniMetric label="Before" value={`${route.distanceBeforeKm} km`} />
        <MiniMetric label="After" value={`${route.distanceAfterKm} km`} />
        <MiniMetric label="Saved" value={`${route.savedPercent}%`} className="text-emerald-400" />
        <MiniMetric
          label="Time"
          value={`${route.timing?.totalTimeMinutes ?? route.durationMinutes} min`}
        />
      </div>

      <div className="grid grid-cols-2 gap-1 px-2 pb-2">
        <div className="bg-slate-700/30 rounded overflow-hidden">
          <div className="px-2 py-1 border-b border-slate-700">
            <h4 className="text-[10px] font-semibold text-slate-400 uppercase">Assigned order</h4>
          </div>
          <div className="max-h-32 overflow-y-auto">
            <MiniStopList stops={route.stopsBefore} color="text-slate-400" />
          </div>
        </div>
        <div className="bg-slate-700/30 rounded overflow-hidden">
          <div className="px-2 py-1 border-b border-slate-700">
            <h4 className="text-[10px] font-semibold text-slate-400 uppercase">Optimized order</h4>
          </div>
          <div className="max-h-32 overflow-y-auto">
            <MiniStopList stops={route.stopsAfter} colorHex={color} />
          </div>
        </div>
      </div>
    </div>
  );
}

function MiniMetric({
  label,
  value,
  className = 'text-slate-200',
}: {
  label: string;
  value: string;
  className?: string;
}) {
  return (
    <div className="text-center p-1.5 rounded bg-slate-700/50">
      <p className="text-[10px] text-slate-500">{label}</p>
      <p className={`text-xs font-bold ${className}`}>{value}</p>
    </div>
  );
}

function MiniStopList({
  stops,
  color,
  colorHex,
}: {
  stops: TestRoutingStop[];
  color?: string;
  colorHex?: string;
}) {
  return (
    <div className="divide-y divide-slate-700/30">
      {stops.map((stop) => (
        <div key={stop.seq} className="px-2 py-0.5 flex gap-1 items-baseline">
          <span
            className={`text-[10px] font-bold flex-shrink-0 ${colorHex ? '' : color ?? ''}`}
            style={colorHex ? { color: colorHex } : undefined}
          >
            {stop.seq}
          </span>
          <span className="text-[10px] text-slate-300 truncate">{stop.recipient}</span>
        </div>
      ))}
    </div>
  );
}
