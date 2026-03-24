import { useRef, useState, useCallback, useEffect } from 'react';
import { useParams } from 'react-router';
import { useRouteMapData, getRouteFromMapData } from '@/api/hooks/useRouteMapData';
import { MapCanvas, type MapCanvasHandle } from '@/components/maps/MapCanvas';
import { StopListPanel } from '@/components/panels/StopListPanel';
import { RouteSummaryBar } from '@/components/panels/RouteSummaryBar';
import { StopMarkersLayer } from '@/components/maps/layers/StopMarkersLayer';
import { RoutePolylineLayer } from '@/components/maps/layers/RoutePolylineLayer';
import { VehicleLayer } from '@/components/maps/layers/VehicleLayer';
import { BottomSheet, type BottomSheetState } from '@/components/bottom-sheet/BottomSheet';
import { TopBar } from '@/components/layout/TopBar';
import { NavigationSidebar } from '@/components/layout/NavigationSidebar';
import { SHEET_HEIGHTS } from '@/components/bottom-sheet/useBottomSheet';
import type { StopData } from '@/api/types';

/**
 * Driver route page — focused on delivery execution.
 * Shows the assigned route with stops, ETAs, and vehicle auto-tracking.
 * The first PENDING stop is highlighted as the current stop.
 * No metrics or comparison data — drivers only need delivery info.
 */
export function DriverRoutePage() {
  const { publicId } = useParams<{ publicId: string }>();
  const mapRef = useRef<MapCanvasHandle>(null);
  const [selectedSequence, setSelectedSequence] = useState<number | null>(null);
  const [navOpen, setNavOpen] = useState(false);
  const [sheetState, setSheetState] = useState<BottomSheetState>('collapsed');

  const contentHeight = window.innerHeight * SHEET_HEIGHTS[sheetState] - 64;

  const { mapData, isLoading, error, sseConnected } = useRouteMapData(publicId);
  const { route, stops, vehiclePosition } = getRouteFromMapData(mapData);

  // Find the current stop (first PENDING stop)
  const currentStop = stops.find((s) => !s.isOrigin && s.status === 'PENDING') ?? null;

  // Auto-select current stop on first load
  useEffect(() => {
    if (currentStop && selectedSequence === null) {
      setSelectedSequence(currentStop.sequence);
    }
  }, [currentStop, selectedSequence]);

  // Auto-track vehicle position: when vehicle moves, center map on it
  useEffect(() => {
    if (vehiclePosition) {
      const bottomPadding = window.innerHeight * SHEET_HEIGHTS[sheetState];
      mapRef.current?.flyTo(vehiclePosition.lng, vehiclePosition.lat, 14, { bottom: bottomPadding });
    }
  }, [vehiclePosition?.lat, vehiclePosition?.lng, sheetState]);

  const handleStopClick = useCallback(
    (sequence: number) => {
      setSelectedSequence(sequence);
      const stop = stops.find((s) => s.sequence === sequence);
      if (stop?.lat != null && stop?.lng != null) {
        const bottomPadding = window.innerHeight * SHEET_HEIGHTS[sheetState];
        mapRef.current?.flyTo(stop.lng, stop.lat, undefined, { bottom: bottomPadding });
      }
    },
    [stops, sheetState],
  );

  // Map-layer stop data (needs lat/lng required)
  const markerStops = stops
    .filter((s): s is StopData & { lat: number; lng: number } => s.lat != null && s.lng != null)
    .map((s) => ({
      lat: s.lat,
      lng: s.lng,
      sequence: s.sequence,
      status: s.status,
      address: s.address,
    }));

  // Vehicle marker data
  const vehicleMarkers =
    vehiclePosition && route?.vehicleName
      ? [
          {
            publicId: mapData?.vehiclePublicId ?? 'vehicle',
            name: route.vehicleName,
            lat: vehiclePosition.lat,
            lng: vehiclePosition.lng,
            speed: vehiclePosition.speed,
            course: vehiclePosition.course,
          },
        ]
      : [];

  // Progress stats
  const nonOriginStops = stops.filter((s) => !s.isOrigin);
  const deliveredCount = nonOriginStops.filter((s) => s.status === 'DELIVERED').length;
  const totalCount = nonOriginStops.length;

  return (
    <div className="flex flex-col h-screen w-full">
      {navOpen && <NavigationSidebar mode="overlay" onClose={() => setNavOpen(false)} />}
      <TopBar compact onMenuClick={() => setNavOpen(true)} />
      <div className="flex-1 relative overflow-hidden">
        <MapCanvas ref={mapRef}>
          {route?.polyline && (
            <RoutePolylineLayer
              id={route.publicId}
              polyline={route.polyline}
              color={route.color}
            />
          )}
          {route && (
            <StopMarkersLayer
              stops={markerStops}
              keyPrefix={`driver-${route.publicId}-`}
              onStopClick={handleStopClick}
              routeColor={route.color}
              selectedSequence={selectedSequence ?? currentStop?.sequence}
            />
          )}
          {vehicleMarkers.length > 0 && <VehicleLayer vehicles={vehicleMarkers} />}
        </MapCanvas>
        <BottomSheet
          state={sheetState}
          onStateChange={setSheetState}
          title={route?.name ?? 'Route'}
          isLoading={isLoading}
          error={error}
          loadingText="Loading route..."
        >
          {route && <div className="px-4 pb-4 space-y-3">
            {/* Always visible: summary bar + SSE indicator */}
            <div className="flex items-center gap-2">
              <RouteSummaryBar
                status={route.status ?? ''}
                deliveredCount={deliveredCount}
                totalCount={totalCount}
                nextEta={currentStop?.etaTime}
              />
              {sseConnected && (
                <span className="w-2 h-2 rounded-full bg-emerald-500 flex-shrink-0" title="Live updates active" />
              )}
            </div>

            {/* Medium zone: progress bar (visible when enough space) */}
            {contentHeight >= 200 && (
              <div className="bg-slate-800/60 rounded-lg p-3 border border-slate-700/40">
                <div className="flex items-center justify-between mb-2">
                  <span className="text-[10px] text-slate-500 uppercase tracking-wider">Progress</span>
                  <span className="text-xs font-medium text-white">
                    {deliveredCount}/{totalCount}
                  </span>
                </div>
                <div className="w-full h-2 bg-slate-700 rounded-full overflow-hidden">
                  <div
                    className="h-full bg-emerald-500 rounded-full transition-all duration-500"
                    style={{ width: totalCount > 0 ? `${(deliveredCount / totalCount) * 100}%` : '0%' }}
                  />
                </div>
                {currentStop && (
                  <div className="mt-2 text-xs text-slate-400">
                    Next: <span className="text-white">{currentStop.address}</span>
                    {currentStop.etaTime && (
                      <span className="text-blue-400 ml-1">ETA {currentStop.etaTime}</span>
                    )}
                  </div>
                )}
              </div>
            )}

            {/* Always visible: stops */}
            <div>
              <div className="text-[10px] text-slate-500 uppercase tracking-wider mb-2">
                Stops ({totalCount})
              </div>
              <StopListPanel
                stops={stops}
                selectedSequence={selectedSequence ?? currentStop?.sequence}
                onStopClick={handleStopClick}
                showEta
                maxItems={contentHeight < 200 ? 2 : undefined}
              />
            </div>
          </div>}
        </BottomSheet>
      </div>
    </div>
  );
}
