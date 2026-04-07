import { useRef, useState, useEffect, useCallback, useMemo } from 'react';
import { useTestRoutingData } from '@/api/hooks/useTestRoutingData';
import { usePageLayout } from '@/api/hooks/usePageLayout';
import { MapCanvas, type MapCanvasHandle } from '@/components/maps/MapCanvas';
import { RoutePolylineLayer } from '@/components/maps/layers/RoutePolylineLayer';
import { StopMarkersLayer } from '@/components/maps/layers/StopMarkersLayer';
import { StopPopup } from '@/components/maps/shared/StopPopup';
import { ROUTE_COLORS } from '@/components/maps/shared/colors';
import { BottomSheet, type BottomSheetState } from '@/components/bottom-sheet/BottomSheet';
import { WidgetRenderer } from '@/components/bottom-sheet/WidgetRenderer';


const SHEET_HEIGHTS: Record<BottomSheetState, number> = {
  collapsed: 0.15,
  half: 0.50,
  full: 0.85,
};

export function TestRoutingPage() {
  const { data, isLoading, error } = useTestRoutingData();
  const { layout } = usePageLayout('test_routing');
  const mapRef = useRef<MapCanvasHandle>(null);
  const [sheetState, setSheetState] = useState<BottomSheetState>('half');
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
    (routeIdx: number, sequence: number) => {
      setHighlightedRouteIdx(routeIdx);
      if (sheetState === 'collapsed') setSheetState('half');
      if (!data) return;
      const route = data.routesData[routeIdx];
      if (!route) return;
      const stop = route.stopsAfter.find((s) => s.seq === sequence);
      if (!stop?.lat || !stop?.lng) return;
      const bottomPadding = window.innerHeight * SHEET_HEIGHTS[sheetState];
      mapRef.current?.flyTo(stop.lng, stop.lat, 16, { bottom: bottomPadding });
    },
    [data, sheetState],
  );

  // Page data passed to all widgets
  const pageData = useMemo(
    () => ({
      metrics: data?.metrics,
      routesData: data?.routesData,
      highlightedRouteIdx,
      onRouteSelect: handleRouteSelect,
    }),
    [data?.metrics, data?.routesData, highlightedRouteIdx, handleRouteSelect],
  );

  // Dynamic BottomSheet title based on loading state
  const sheetTitle = isLoading
    ? 'Optimizing routes...'
    : error
      ? 'Optimization Error'
      : 'Test Routing Results';

  const origin = data?.origin;
  const routesData = data?.routesData;
  const sheetHeightPx = window.innerHeight * SHEET_HEIGHTS[sheetState];

  return (
    <>
      <MapCanvas
          ref={mapRef}
          initialCenter={origin ? { lat: origin.lat, lng: origin.lng } : undefined}
          initialZoom={12}
        >
          {/* Original route (dashed red) */}
          {data?.polylineBefore && (
            <RoutePolylineLayer
              id="original"
              polyline={data.polylineBefore}
              color="#EF4444"
              dashed
              opacity={highlightedRouteIdx !== null ? 0.15 : 0.6}
            />
          )}

          {/* Optimized routes */}
          {routesData?.map((route, idx) => {
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
          {routesData?.map((route, idx) => {
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
                onStopClick={(seq) => handleStopClick(idx, seq)}
                renderPopup={(stop) => (
                  <StopPopup
                    sequence={stop.sequence}
                    address={stop.address}
                    status={stop.status}
                  />
                )}
              />
            );
          })}
        </MapCanvas>

        {/* Fit all button */}
        {data && (
          <button
            type="button"
            className="absolute top-4 right-4 bg-slate-800/90 text-slate-200 px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-slate-700 transition-colors border border-slate-600 z-10"
            onClick={() => mapRef.current?.fitBounds(allPoints)}
          >
            Fit all
          </button>
        )}

        {/* Map overlay legend */}
        {routesData && (
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
        )}

        {/* Bottom Sheet with dynamic widgets */}
        <BottomSheet
          state={sheetState}
          onStateChange={setSheetState}
          title={sheetTitle}
          isLoading={isLoading}
          error={error}
          loadingText="Running route optimization..."
        >
          <WidgetRenderer
            layout={layout}
            sheetState={sheetState}
            pageData={pageData}
          />
        </BottomSheet>
    </>
  );
}
