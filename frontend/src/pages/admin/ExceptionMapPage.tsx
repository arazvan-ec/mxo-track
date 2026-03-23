import { useState, useRef, useEffect, useMemo } from 'react';
import { MapCanvas, type MapCanvasHandle } from '@/components/maps/MapCanvas';
import { ExceptionHeatmapLayer } from '@/components/maps/layers/ExceptionHeatmapLayer';
import { BottomSheet, type BottomSheetState } from '@/components/bottom-sheet/BottomSheet';
import { TopBar } from '@/components/layout/TopBar';
import { NavigationSidebar } from '@/components/layout/NavigationSidebar';
import {
  useExceptionMapData,
  type ExceptionPoint,
} from '@/api/hooks/useExceptionMapData';

/** Group exceptions by type and return sorted array. */
function groupByType(exceptions: ExceptionPoint[]) {
  const map = new Map<string, ExceptionPoint[]>();
  for (const ex of exceptions) {
    const group = map.get(ex.type) ?? [];
    group.push(ex);
    map.set(ex.type, group);
  }
  return Array.from(map.entries())
    .map(([type, items]) => ({ type, count: items.length }))
    .sort((a, b) => b.count - a.count);
}

export function ExceptionMapPage() {
  const mapRef = useRef<MapCanvasHandle>(null);

  // Default date range: last 30 days
  const today = new Date().toISOString().slice(0, 10);
  const thirtyDaysAgo = new Date(Date.now() - 30 * 86400000)
    .toISOString()
    .slice(0, 10);

  const [from, setFrom] = useState(thirtyDaysAgo);
  const [to, setTo] = useState(today);
  const [viewMode, setViewMode] = useState<'heatmap' | 'points'>('heatmap');
  const [navOpen, setNavOpen] = useState(false);
  const [sheetState, setSheetState] = useState<BottomSheetState>('collapsed');

  const { exceptions, isLoading, error } = useExceptionMapData(from, to);

  const grouped = useMemo(() => groupByType(exceptions), [exceptions]);

  // Auto-fit bounds when exceptions change
  useEffect(() => {
    if (exceptions.length > 0) {
      mapRef.current?.fitBounds(exceptions);
    }
  }, [exceptions]);

  return (
    <div className="flex flex-col h-screen w-full">
      {navOpen && <NavigationSidebar mode="overlay" onClose={() => setNavOpen(false)} />}
      <TopBar compact onMenuClick={() => setNavOpen(true)} />
      <div className="flex-1 relative overflow-hidden">
        <MapCanvas ref={mapRef}>
          <ExceptionHeatmapLayer exceptions={exceptions} mode={viewMode} />
        </MapCanvas>
        <BottomSheet state={sheetState} onStateChange={setSheetState} title="Excepciones">
          <div className="px-4 pb-4 space-y-4">
            {/* Date filters */}
            <div>
              <label className="block text-xs font-medium text-slate-400 mb-1.5">
                Desde
              </label>
              <input
                type="date"
                value={from}
                onChange={(e) => setFrom(e.target.value)}
                className="w-full bg-slate-800/80 border border-slate-700/50 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-red-500/50 focus:ring-1 focus:ring-red-500/30"
              />
            </div>

            <div>
              <label className="block text-xs font-medium text-slate-400 mb-1.5">
                Hasta
              </label>
              <input
                type="date"
                value={to}
                onChange={(e) => setTo(e.target.value)}
                className="w-full bg-slate-800/80 border border-slate-700/50 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-red-500/50 focus:ring-1 focus:ring-red-500/30"
              />
            </div>

            {/* View mode toggle */}
            <div className="flex gap-2">
              <button
                onClick={() => setViewMode('heatmap')}
                className={`flex-1 px-3 py-1.5 text-xs font-medium rounded-lg transition-colors ${
                  viewMode === 'heatmap'
                    ? 'bg-red-500/20 text-red-400 border border-red-500/50'
                    : 'bg-slate-800/50 text-slate-400 border border-slate-700/30 hover:text-slate-200'
                }`}
              >
                Heatmap
              </button>
              <button
                onClick={() => setViewMode('points')}
                className={`flex-1 px-3 py-1.5 text-xs font-medium rounded-lg transition-colors ${
                  viewMode === 'points'
                    ? 'bg-red-500/20 text-red-400 border border-red-500/50'
                    : 'bg-slate-800/50 text-slate-400 border border-slate-700/30 hover:text-slate-200'
                }`}
              >
                Puntos
              </button>
            </div>

            {/* Summary */}
            <div className="bg-slate-800/60 rounded-lg p-3 border border-slate-700/40">
              <div className="text-[10px] text-slate-500 uppercase tracking-wider mb-2">
                Resumen
              </div>
              <div className="text-lg font-bold text-white">
                {isLoading ? '...' : exceptions.length}
              </div>
              <div className="text-xs text-slate-400">excepciones encontradas</div>
              {error && (
                <div className="text-xs text-red-400 mt-1">
                  Error cargando datos
                </div>
              )}
            </div>

            {/* Grouped by type */}
            {grouped.length > 0 && (
              <div className="border-t border-slate-700/50 pt-4">
                <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500 mb-3">
                  Por tipo
                </p>
                <div className="space-y-1.5">
                  {grouped.map((g) => (
                    <div
                      key={g.type}
                      className="flex items-center justify-between bg-slate-800/50 rounded-lg px-3 py-2 border border-slate-700/30"
                    >
                      <span className="text-sm text-slate-200">{g.type}</span>
                      <span className="bg-red-500/20 text-red-400 text-xs font-bold px-2 py-0.5 rounded-full">
                        {g.count}
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>
        </BottomSheet>
      </div>
    </div>
  );
}
