import { useState, useRef, useEffect, useMemo } from 'react';
import { MapCanvas, type MapCanvasHandle } from '@/components/maps/MapCanvas';
import { ExceptionHeatmapLayer } from '@/components/maps/layers/ExceptionHeatmapLayer';
import { BottomSheet, type BottomSheetState } from '@/components/bottom-sheet/BottomSheet';

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
    <>
      <MapCanvas ref={mapRef}>
          <ExceptionHeatmapLayer exceptions={exceptions} mode={viewMode} />
        </MapCanvas>
        <BottomSheet
          state={sheetState}
          onStateChange={setSheetState}
          title="Excepciones"
          isLoading={isLoading}
          error={error}
          loadingText="Cargando excepciones..."
        >
          <div className="px-4 pb-4 space-y-4">
            {/* Date filters */}
            <div>
              <label className="block text-xs font-medium mb-1.5" style={{ color: 'var(--color-text-secondary)' }}>
                Desde
              </label>
              <input
                type="date"
                value={from}
                onChange={(e) => setFrom(e.target.value)}
                className="w-full theme-card-overlay border rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-red-500/50 focus:ring-1 focus:ring-red-500/30"
                style={{ borderColor: 'var(--color-border-subtle)', color: 'var(--color-text-primary)' }}
              />
            </div>

            <div>
              <label className="block text-xs font-medium mb-1.5" style={{ color: 'var(--color-text-secondary)' }}>
                Hasta
              </label>
              <input
                type="date"
                value={to}
                onChange={(e) => setTo(e.target.value)}
                className="w-full theme-card-overlay border rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-red-500/50 focus:ring-1 focus:ring-red-500/30"
                style={{ borderColor: 'var(--color-border-subtle)', color: 'var(--color-text-primary)' }}
              />
            </div>

            {/* View mode toggle */}
            <div className="flex gap-2">
              <button
                onClick={() => setViewMode('heatmap')}
                className={`flex-1 px-3 py-1.5 text-xs font-medium rounded-lg transition-colors ${
                  viewMode === 'heatmap'
                    ? 'bg-red-500/20 text-red-400 border border-red-500/50'
                    : 'theme-card-overlay border'
                }`}
                style={viewMode !== 'heatmap' ? { color: 'var(--color-text-secondary)', borderColor: 'var(--color-border-subtle)' } : undefined}
              >
                Heatmap
              </button>
              <button
                onClick={() => setViewMode('points')}
                className={`flex-1 px-3 py-1.5 text-xs font-medium rounded-lg transition-colors ${
                  viewMode === 'points'
                    ? 'bg-red-500/20 text-red-400 border border-red-500/50'
                    : 'theme-card-overlay border'
                }`}
                style={viewMode !== 'points' ? { color: 'var(--color-text-secondary)', borderColor: 'var(--color-border-subtle)' } : undefined}
              >
                Puntos
              </button>
            </div>

            {/* Summary */}
            <div className="theme-card-overlay rounded-lg p-3 border" style={{ borderColor: 'var(--color-border-subtle)' }}>
              <div className="text-[10px] uppercase tracking-wider mb-2" style={{ color: 'var(--color-text-muted)' }}>
                Resumen
              </div>
              <div className="text-lg font-bold text-white">
                {exceptions.length}
              </div>
              <div className="text-xs" style={{ color: 'var(--color-text-secondary)' }}>excepciones encontradas</div>
            </div>

            {/* Grouped by type */}
            {grouped.length > 0 && (
              <div className="border-t pt-4" style={{ borderColor: 'var(--color-border-subtle)' }}>
                <p className="text-[10px] font-semibold uppercase tracking-wider mb-3" style={{ color: 'var(--color-text-muted)' }}>
                  Por tipo
                </p>
                <div className="space-y-1.5">
                  {grouped.map((g) => (
                    <div
                      key={g.type}
                      className="flex items-center justify-between theme-card-overlay rounded-lg px-3 py-2 border"
                      style={{ borderColor: 'var(--color-border-subtle)' }}
                    >
                      <span className="text-sm" style={{ color: 'var(--color-text-primary)' }}>{g.type}</span>
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
    </>
  );
}
