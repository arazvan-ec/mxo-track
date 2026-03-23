import type { TestRoutingRoute } from '@/api/hooks/useTestRoutingData';
import { ROUTE_COLORS } from '@/components/maps/shared/colors';
import type { WidgetProps } from './types';

interface MapLegendData {
  routesData: TestRoutingRoute[];
}

export function MapLegendWidget({ data }: WidgetProps) {
  const { routesData } = data as MapLegendData;
  if (!routesData) return null;

  return (
    <div className="px-4 pb-3">
      <div className="bg-slate-800/60 rounded-lg px-3 py-2 space-y-1.5">
        <h4 className="text-[10px] font-semibold text-slate-400 uppercase mb-1">Legend</h4>
        <div className="flex items-center gap-2">
          <div className="w-6 h-0.5 border-t-2 border-dashed border-red-500" />
          <span className="text-xs text-slate-300">Original</span>
        </div>
        {routesData.map((route, idx) => (
          <div key={route.name} className="flex items-center gap-2">
            <div
              className="w-6 h-0.5 rounded"
              style={{ backgroundColor: ROUTE_COLORS[idx % ROUTE_COLORS.length] }}
            />
            <span className="text-xs text-slate-300">{route.name}</span>
          </div>
        ))}
      </div>
    </div>
  );
}
