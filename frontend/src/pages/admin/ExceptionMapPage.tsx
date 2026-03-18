import { useState, useRef, useEffect, useMemo } from 'react';
import { MapCanvas, type MapCanvasHandle } from '@/components/maps/MapCanvas';
import { ExceptionHeatmapLayer } from '@/components/maps/layers/ExceptionHeatmapLayer';
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

  const { exceptions, isLoading, error } = useExceptionMapData(from, to);

  const grouped = useMemo(() => groupByType(exceptions), [exceptions]);

  // Auto-fit bounds when exceptions change
  useEffect(() => {
    if (exceptions.length > 0) {
      mapRef.current?.fitBounds(exceptions);
    }
  }, [exceptions]);

  return (
    <div className="relative flex h-full w-full overflow-hidden">
      {/* Sidebar */}
      <div className="absolute top-0 left-0 bottom-0 z-[999] w-80 flex flex-col bg-slate-900/95 backdrop-blur-xl border-r border-slate-700/50">
        {/* Back link */}
        <a
          href="/app/admin/fleet-map"
          className="flex-shrink-0 flex items-center gap-2 px-5 pt-4 pb-2 text-slate-400 hover:text-white transition-colors text-sm font-medium"
        >
          <svg
            className="w-4 h-4"
            fill="none"
            viewBox="0 0 24 24"
            strokeWidth={2}
            stroke="currentColor"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"
            />
          </svg>
          Volver
        </a>

        {/* Brand header */}
        <div className="flex-shrink-0 px-5 pt-2 pb-3">
          <div className="flex items-center gap-2.5 mb-4">
            <div className="w-8 h-8 rounded-lg bg-red-600 flex items-center justify-center">
              <svg
                className="w-5 h-5 text-white"
                fill="none"
                viewBox="0 0 24 24"
                strokeWidth={1.5}
                stroke="currentColor"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"
                />
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"
                />
              </svg>
            </div>
            <div>
              <h1 className="text-white font-bold text-base tracking-tight">
                Excepciones
              </h1>
              <p className="text-slate-500 text-[10px] uppercase tracking-widest">
                Mapa
              </p>
            </div>
          </div>
        </div>

        {/* Filters */}
        <div className="flex-1 overflow-y-auto px-5 pb-4">
          <div className="mb-4">
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

          <div className="mb-4">
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
          <div className="flex gap-2 mb-4">
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
          <div className="bg-slate-800/60 rounded-lg p-3 border border-slate-700/40 mb-4">
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
      </div>

      {/* Top header bar */}
      <div
        className="absolute top-0 left-80 right-0 z-[1000] h-14 flex items-center justify-between px-6"
        style={{
          background:
            'linear-gradient(to bottom, rgba(15,23,42,0.85) 0%, rgba(15,23,42,0.4) 70%, transparent 100%)',
        }}
      >
        <h2 className="text-white font-semibold text-sm">
          Mapa de Excepciones
        </h2>
        <div className="flex items-center gap-3">
          <span className="text-slate-400 text-xs">
            {exceptions.length} excepciones
          </span>
        </div>
      </div>

      {/* Map */}
      <div className="absolute inset-0">
        <MapCanvas ref={mapRef}>
          <ExceptionHeatmapLayer exceptions={exceptions} mode={viewMode} />
        </MapCanvas>
      </div>
    </div>
  );
}
