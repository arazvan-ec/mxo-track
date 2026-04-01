import type { TestRoutingRoute } from '@/api/hooks/useTestRoutingData';
import { ROUTE_COLORS } from '@/components/maps/shared/colors';
import type { WidgetProps } from './types';

interface MapLegendData {
  /** TestRouting format */
  routesData?: TestRoutingRoute[];
  /** Generic format: routes with name and color */
  routes?: Array<{ name: string; color: string }>;
  /** Analysis format: show planned vs actual */
  showComparison?: boolean;
}

export function MapLegendWidget({ data }: WidgetProps) {
  const { routesData, routes, showComparison } = data as MapLegendData;

  const legendItems: Array<{ name: string; color: string; dashed?: boolean }> = [];

  if (showComparison) {
    legendItems.push({ name: 'Ruta planificada', color: '#3b82f6' });
    legendItems.push({ name: 'Ruta ejecutada', color: '#ef4444', dashed: true });
  } else if (routes) {
    routes.forEach((r) => legendItems.push({ name: r.name, color: r.color }));
  } else if (routesData) {
    legendItems.push({ name: 'Original', color: '#ef4444', dashed: true });
    routesData.forEach((r, idx) =>
      legendItems.push({ name: r.name, color: ROUTE_COLORS[idx % ROUTE_COLORS.length] }),
    );
  }

  if (legendItems.length === 0) return null;

  return (
    <div className="px-4 pb-3">
      <div className="bg-slate-800/60 rounded-lg px-3 py-2 space-y-1.5">
        <h4 className="text-[10px] font-semibold text-slate-400 uppercase mb-1">Legend</h4>
        {legendItems.map((item) => (
          <div key={item.name} className="flex items-center gap-2">
            <div
              className="w-6 h-0.5 rounded flex-shrink-0"
              style={
                item.dashed
                  ? {
                      backgroundImage: `repeating-linear-gradient(90deg, ${item.color} 0, ${item.color} 4px, transparent 4px, transparent 8px)`,
                      height: '3px',
                    }
                  : { backgroundColor: item.color }
              }
            />
            <span className="text-xs text-slate-300">{item.name}</span>
          </div>
        ))}
      </div>
    </div>
  );
}
