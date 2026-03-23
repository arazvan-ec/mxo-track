import { useRef, useState, useEffect, useCallback } from 'react';
import { useTestRoutingData } from '@/api/hooks/useTestRoutingData';
import type { TestRoutingRoute, TestRoutingStop } from '@/api/hooks/useTestRoutingData';
import { MapCanvas, type MapCanvasHandle } from '@/components/maps/MapCanvas';
import { RoutePolylineLayer } from '@/components/maps/layers/RoutePolylineLayer';
import { StopMarkersLayer } from '@/components/maps/layers/StopMarkersLayer';
import { ROUTE_COLORS } from '@/components/maps/shared/colors';
import { BottomSheet, type BottomSheetState } from '@/components/bottom-sheet/BottomSheet';
import { MetricPairs } from '@/components/metrics/MetricPairs';
import { TopBar } from '@/components/layout/TopBar';
import { NavigationSidebar } from '@/components/layout/NavigationSidebar';

const SHEET_HEIGHTS: Record<BottomSheetState, number> = {
  collapsed: 0.15,
  half: 0.50,
  full: 0.85,
};

export function TestRoutingPage() {
  const { data, isLoading, error } = useTestRoutingData();
  const mapRef = useRef<MapCanvasHandle>(null);
  const [navOpen, setNavOpen] = useState(false);
  const [sheetState, setSheetState] = useState<BottomSheetState>('collapsed');
  const [highlightedRouteIdx, setHighlightedRouteIdx] = useState<number | null>(null);

  // Compute all map points for fitBounds
  const allPoints = data
    ? [
        { lat: data.origin.lat, lng: data.origin.lng },
        ...data.allStopsBefore.map((s) => ({ lat: s.lat, lng: s.lng })),
      ]
    : [];

  // FitBounds when sheet state changes
  useEffect(() => {
    if (!data || allPoints.length === 0) return;
    const bottomPadding = window.innerHeight * SHEET_HEIGHTS[sheetState];
    mapRef.current?.fitBounds(allPoints, {
      padding: { top: 80, right: 80, bottom: bottomPadding + 20, left: 80 },
    });
  }, [sheetState]); // eslint-disable-line react-hooks/exhaustive-deps

  const handleRouteSelect = useCallback(
    (idx: number) => {
      if (!data) return;
      const newIdx = highlightedRouteIdx === idx ? null : idx;
      setHighlightedRouteIdx(newIdx);
      if (newIdx !== null) {
        const points = data.routesData[newIdx].stopsAfter
          .filter((s) => s.lat && s.lng)
          .map((s) => ({ lat: s.lat, lng: s.lng }));
        if (points.length > 0) {
          mapRef.current?.fitBounds(points);
        }
      }
    },
    [data, highlightedRouteIdx],
  );

  const handleStopClick = useCallback(
    (routeIdx: number) => {
      setHighlightedRouteIdx(routeIdx);
      if (sheetState === 'collapsed') setSheetState('half');
    },
    [sheetState],
  );

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-screen bg-slate-900">
        <div className="text-center">
          <div className="animate-spin h-8 w-8 border-2 border-blue-500 border-t-transparent rounded-full mx-auto mb-4" />
          <p className="text-slate-400 text-sm">Running route optimization...</p>
          <p className="text-slate-500 text-xs mt-1">This may take a few seconds</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex items-center justify-center h-screen bg-slate-900">
        <div className="text-red-400 text-center">
          <p className="text-lg font-medium mb-2">Optimization Error</p>
          <p className="text-sm text-red-500">{error.message}</p>
        </div>
      </div>
    );
  }

  if (!data) return null;

  const { origin, routesData, metrics } = data;
  const sheetHeightPx = window.innerHeight * SHEET_HEIGHTS[sheetState];

  return (
    <div className="flex flex-col h-screen w-full">
      {/* Navigation sidebar — overlay */}
      {navOpen && (
        <NavigationSidebar mode="overlay" onClose={() => setNavOpen(false)} />
      )}

      {/* Top bar */}
      <TopBar compact onMenuClick={() => setNavOpen(true)} />

      {/* Map area */}
      <div className="flex-1 relative overflow-hidden">
        <MapCanvas
          ref={mapRef}
          initialCenter={{ lat: origin.lat, lng: origin.lng }}
          initialZoom={12}
        >
          {/* Original route (dashed red) */}
          {data.polylineBefore && (
            <RoutePolylineLayer
              id="original"
              polyline={data.polylineBefore}
              color="#EF4444"
              dashed
              opacity={highlightedRouteIdx !== null ? 0.15 : 0.6}
            />
          )}

          {/* Optimized routes */}
          {routesData.map((route, idx) => {
            if (!route.polylineAfter) return null;
            const color = ROUTE_COLORS[idx % ROUTE_COLORS.length];
            return (
              <RoutePolylineLayer
                key={route.name}
                id={`opt-${idx}`}
                polyline={route.polylineAfter}
                color={color}
                opacity={
                  highlightedRouteIdx === null
                    ? 0.85
                    : highlightedRouteIdx === idx
                      ? 1
                      : 0.3
                }
                lineWidth={highlightedRouteIdx === idx ? 6 : 4}
              />
            );
          })}

          {/* Stop markers */}
          {routesData.map((route, idx) => {
            const routeColor = ROUTE_COLORS[idx % ROUTE_COLORS.length];
            return (
              <StopMarkersLayer
                key={`stops-${idx}`}
                keyPrefix={`route-${idx}-`}
                routeColor={routeColor}
                stops={route.stopsAfter.map((s) => ({
                  lat: s.lat,
                  lng: s.lng,
                  sequence: s.seq,
                  status: 'PENDING',
                  address: s.address,
                }))}
                onStopClick={() => handleStopClick(idx)}
              />
            );
          })}
        </MapCanvas>

        {/* Fit all button */}
        <button
          type="button"
          className="absolute top-4 right-4 bg-slate-800/90 text-slate-200 px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-slate-700 transition-colors border border-slate-600 z-10"
          onClick={() => mapRef.current?.fitBounds(allPoints)}
        >
          Fit all
        </button>

        {/* Legend — positioned above bottom sheet */}
        <div
          className="absolute left-4 bg-slate-800/90 border border-slate-700 rounded-lg px-3 py-2 space-y-1.5 z-10 transition-all duration-300"
          style={{ bottom: sheetHeightPx + 16 }}
        >
          <div className="flex items-center gap-2">
            <div className="w-6 h-0.5 border-t-2 border-dashed border-red-500" />
            <span className="text-xs text-slate-300">Original</span>
          </div>
          {routesData.map((route, idx) => (
            <div key={route.name} className="flex items-center gap-2">
              <div
                className="w-6 h-0.5 rounded"
                style={{
                  backgroundColor: ROUTE_COLORS[idx % ROUTE_COLORS.length],
                }}
              />
              <span className="text-xs text-slate-300">{route.name}</span>
            </div>
          ))}
        </div>

        {/* Bottom Sheet */}
        <BottomSheet
          state={sheetState}
          onStateChange={setSheetState}
          title="Test Routing Results"
        >
          <MetricPairs
            metrics={metrics}
            expanded={sheetState !== 'collapsed'}
          />

          {sheetState !== 'collapsed' && (
            <div className="px-4 pb-4 space-y-3">
              {routesData.map((route, idx) => (
                <RouteCard
                  key={route.name}
                  route={route}
                  color={ROUTE_COLORS[idx % ROUTE_COLORS.length]}
                  highlighted={highlightedRouteIdx === idx}
                  onSelect={() => handleRouteSelect(idx)}
                />
              ))}
            </div>
          )}
        </BottomSheet>
      </div>
    </div>
  );
}

/* ── Route Card ─────────────────────────────────────────────── */

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
      {/* Header — clickable */}
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
          <h3 className="text-sm font-semibold text-slate-100">
            {route.name}
          </h3>
          <span className="text-xs text-slate-400">{route.vehicle}</span>
        </div>
        <span className="text-xs text-slate-400">
          {route.stopCount} stops
        </span>
      </button>

      {/* Route metrics */}
      <div className="grid grid-cols-4 gap-1 px-2 py-2">
        <MiniMetric label="Before" value={`${route.distanceBeforeKm} km`} />
        <MiniMetric label="After" value={`${route.distanceAfterKm} km`} />
        <MiniMetric
          label="Saved"
          value={`${route.savedPercent}%`}
          className="text-emerald-400"
        />
        <MiniMetric
          label="Time"
          value={`${route.timing?.totalTimeMinutes ?? route.durationMinutes} min`}
        />
      </div>

      {/* Side-by-side stop comparison */}
      <div className="grid grid-cols-2 gap-1 px-2 pb-2">
        <div className="bg-slate-700/30 rounded overflow-hidden">
          <div className="px-2 py-1 border-b border-slate-700">
            <h4 className="text-[10px] font-semibold text-slate-400 uppercase">
              Assigned order
            </h4>
          </div>
          <div className="max-h-32 overflow-y-auto">
            <MiniStopList stops={route.stopsBefore} color="text-slate-400" />
          </div>
        </div>
        <div className="bg-slate-700/30 rounded overflow-hidden">
          <div className="px-2 py-1 border-b border-slate-700">
            <h4 className="text-[10px] font-semibold text-slate-400 uppercase">
              Optimized order
            </h4>
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
          <span className="text-[10px] text-slate-300 truncate">
            {stop.recipient}
          </span>
        </div>
      ))}
    </div>
  );
}
