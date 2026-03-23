import type { TestRoutingRoute, TestRoutingStop } from '@/api/hooks/useTestRoutingData';
import { ROUTE_COLORS } from '@/components/maps/shared/colors';
import type { WidgetProps } from './types';

interface RouteCardListData {
  routesData: TestRoutingRoute[];
  highlightedRouteIdx: number | null;
  onRouteSelect: (idx: number) => void;
}

export function RouteCardListWidget({ data }: WidgetProps) {
  const { routesData, highlightedRouteIdx, onRouteSelect } = data as RouteCardListData;
  if (!routesData) return null;

  return (
    <div className="px-4 pb-4 space-y-3">
      {routesData.map((route, idx) => (
        <RouteCard
          key={route.name}
          route={route}
          color={ROUTE_COLORS[idx % ROUTE_COLORS.length]}
          highlighted={highlightedRouteIdx === idx}
          onSelect={() => onRouteSelect(idx)}
        />
      ))}
    </div>
  );
}

function RouteCard({
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
          <div
            className="w-3 h-3 rounded-full flex-shrink-0"
            style={{ backgroundColor: color }}
          />
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
