import { useRef, useState, useCallback, useEffect, useMemo } from 'react';
import { useParams } from 'react-router';
import { useRouteMapData, getRouteFromMapData } from '@/api/hooks/useRouteMapData';
import { useMe } from '@/api/hooks/useMe';
import { usePageLayout } from '@/api/hooks/usePageLayout';
import { MapCanvas, type MapCanvasHandle } from '@/components/maps/MapCanvas';
import { RouteSummaryBar } from '@/components/panels/RouteSummaryBar';
import { EntityActionPanel } from '@/components/panels/EntityActionPanel';
import { StopMarkersLayer } from '@/components/maps/layers/StopMarkersLayer';
import { RoutePolylineLayer } from '@/components/maps/layers/RoutePolylineLayer';
import { VehicleLayer } from '@/components/maps/layers/VehicleLayer';
import { StopPopup } from '@/components/maps/shared/StopPopup';
import { useMapSelection } from '@/hooks/useMapSelection';
import { BottomSheet, type BottomSheetState } from '@/components/bottom-sheet/BottomSheet';
import { WidgetRenderer } from '@/components/bottom-sheet/WidgetRenderer';
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
  const [navOpen, setNavOpen] = useState(false);
  const [sheetState, setSheetState] = useState<BottomSheetState>('collapsed');
  const { data: me } = useMe();
  const { selection, selectStop, clear } = useMapSelection();
  const { layout } = usePageLayout('driver_route');

  const { mapData, isLoading, error, sseConnected } = useRouteMapData(publicId);
  const { route, stops, vehiclePosition } = getRouteFromMapData(mapData);

  // Find the current stop (first PENDING stop)
  const currentStop = stops.find((s) => !s.isOrigin && s.status === 'PENDING') ?? null;

  const selectedStopSequence =
    selection?.type === 'stop'
      ? (selection.data as { sequence: number }).sequence
      : null;

  // Auto-select current stop on first load
  useEffect(() => {
    if (currentStop && selection === null) {
      selectStop(`stop-${route?.publicId}-${currentStop.sequence}`, {
        sequence: currentStop.sequence,
        address: currentStop.address,
        status: currentStop.status,
        recipientName: currentStop.recipientName,
        recipientPhone: currentStop.recipientPhone,
        shipmentPublicId: currentStop.shipmentPublicId,
        routePublicId: route?.publicId,
        etaTime: currentStop.etaTime,
        lat: currentStop.lat,
        lng: currentStop.lng,
      });
    }
  }, [currentStop, selection, selectStop, route?.publicId]);

  // Auto-track vehicle position: when vehicle moves, center map on it
  useEffect(() => {
    if (vehiclePosition) {
      const bottomPadding = window.innerHeight * SHEET_HEIGHTS[sheetState];
      mapRef.current?.flyTo(vehiclePosition.lng, vehiclePosition.lat, 14, { bottom: bottomPadding });
    }
  }, [vehiclePosition?.lat, vehiclePosition?.lng, sheetState]);

  const handleStopClick = useCallback(
    (sequence: number) => {
      const stop = stops.find((s) => s.sequence === sequence);
      if (!stop) return;

      selectStop(`stop-${route?.publicId}-${sequence}`, {
        sequence: stop.sequence,
        address: stop.address,
        status: stop.status,
        recipientName: stop.recipientName,
        recipientPhone: stop.recipientPhone,
        shipmentPublicId: stop.shipmentPublicId,
        routePublicId: route?.publicId,
        etaTime: stop.etaTime,
        deliveredAt: stop.deliveredAt,
        lat: stop.lat,
        lng: stop.lng,
      });

      if (stop.lat != null && stop.lng != null) {
        const bottomPadding = window.innerHeight * SHEET_HEIGHTS[sheetState];
        mapRef.current?.flyTo(stop.lng, stop.lat, undefined, { bottom: bottomPadding });
      }
    },
    [stops, sheetState, selectStop, route?.publicId],
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
      recipientName: s.recipientName,
      shipmentPublicId: s.shipmentPublicId,
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

  const pageData = useMemo(
    () => ({
      driverName: route?.driverName ?? route?.vehicleName,
      deliveredCount,
      totalCount,
      currentStop: currentStop
        ? { address: currentStop.address, etaTime: currentStop.etaTime }
        : null,
      stops,
      selectedSequence: selectedStopSequence ?? currentStop?.sequence,
      onStopClick: handleStopClick,
      showEta: true,
    }),
    [
      route?.driverName,
      route?.vehicleName,
      deliveredCount,
      totalCount,
      currentStop,
      stops,
      selectedStopSequence,
      handleStopClick,
    ],
  );

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
              selectedSequence={selectedStopSequence ?? currentStop?.sequence}
              renderPopup={(stop) => (
                <StopPopup
                  sequence={stop.sequence}
                  address={stop.address}
                  status={stop.status}
                  recipientName={stop.recipientName}
                  shipmentPublicId={stop.shipmentPublicId}
                />
              )}
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
          {route && <div className="space-y-3">
            {selection && (
              <div className="px-4">
                <EntityActionPanel
                  selection={selection}
                  userRole={me?.role}
                  onClose={clear}
                />
              </div>
            )}

            <div className="px-4 flex items-center gap-2">
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

            <WidgetRenderer layout={layout} sheetState={sheetState} pageData={pageData} />
          </div>}
        </BottomSheet>
      </div>
    </div>
  );
}
