import { useCallback, useRef, useState } from 'react';
import { useParams } from 'react-router';
import { useRouteMapData } from '@/api/hooks/useRouteMapData';
import { useVehicleTrail } from '@/api/hooks/useVehicleTrail';
import { useMe } from '@/api/hooks/useMe';
import { MapCanvas, type MapCanvasHandle } from '@/components/maps/MapCanvas';
import { StopMarkersLayer } from '@/components/maps/layers/StopMarkersLayer';
import { RoutePolylineLayer } from '@/components/maps/layers/RoutePolylineLayer';
import { VehicleLayer } from '@/components/maps/layers/VehicleLayer';
import { VehicleTrailLayer } from '@/components/maps/layers/VehicleTrailLayer';
import { StopPopup } from '@/components/maps/shared/StopPopup';
import { StopListPanel, RouteMetricsPanel, VehicleInfoPanel, RouteSummaryBar, EntityActionPanel } from '@/components/panels';
import { useMapSelection } from '@/hooks/useMapSelection';
import { BottomSheet, type BottomSheetState } from '@/components/bottom-sheet/BottomSheet';
import { TopBar } from '@/components/layout/TopBar';
import { NavigationSidebar } from '@/components/layout/NavigationSidebar';
import { SHEET_HEIGHTS } from '@/components/bottom-sheet/useBottomSheet';
import type { StopData, RouteData } from '@/api/types';

export function RouteDetailPage() {
  const { publicId } = useParams<{ publicId: string }>();
  const { mapData, isLoading, error, sseConnected } = useRouteMapData(publicId);
  const { data: me } = useMe();
  const mapRef = useRef<MapCanvasHandle>(null);
  const [navOpen, setNavOpen] = useState(false);
  const [sheetState, setSheetState] = useState<BottomSheetState>('collapsed');
  const { selection, selectStop, clear } = useMapSelection();

  const contentHeight = window.innerHeight * SHEET_HEIGHTS[sheetState] - 64;

  const route: RouteData | null = mapData?.routes?.[0] ?? null;

  // Vehicle trail
  const { coordinates: trailCoordinates } = useVehicleTrail(
    mapData?.vehiclePublicId ?? null,
  );

  // Selected stop sequence for highlighting
  const selectedStopSequence =
    selection?.type === 'stop'
      ? (selection.data as { sequence: number }).sequence
      : null;

  // Stop click -> select + fly to stop
  const handleStopClick = useCallback(
    (sequence: number) => {
      if (!route) return;
      const stop = route.stops.find((s) => s.sequence === sequence);
      if (!stop) return;

      selectStop(`stop-${route.publicId}-${sequence}`, {
        sequence: stop.sequence,
        address: stop.address,
        status: stop.status,
        recipientName: stop.recipientName,
        recipientPhone: stop.recipientPhone,
        shipmentPublicId: stop.shipmentPublicId,
        routePublicId: route.publicId,
        etaTime: stop.etaTime,
        deliveredAt: stop.deliveredAt,
        exceptionCode: stop.exceptionCode,
        lat: stop.lat,
        lng: stop.lng,
      });

      if (stop.lat != null && stop.lng != null) {
        const bottomPadding = window.innerHeight * SHEET_HEIGHTS[sheetState];
        mapRef.current?.flyTo(stop.lng, stop.lat, 16, { bottom: bottomPadding });
      }
    },
    [route, selectStop, sheetState],
  );

  // Build metrics from route data (merge metrics + timing)
  const metrics = route?.metrics
    ? {
        distanceBeforeKm: route.metrics.distanceBeforeKm as number | undefined,
        distanceAfterKm: route.metrics.distanceAfterKm as number | undefined,
        savingsPercent: route.metrics.savingsPercent as number | undefined,
        drivingTimeMinutes: (route.timing?.drivingTimeMinutes as number | undefined),
        deliveryTimeMinutes: (route.timing?.deliveryTimeMinutes as number | undefined),
        totalTimeMinutes: (route.timing?.totalTimeMinutes as number | undefined),
      }
    : undefined;

  // Build vehicle info
  const vehicleInfo =
    route?.vehicleName || route?.driverName
      ? {
          name: route.vehicleName ?? 'Unknown vehicle',
          driverName: route.driverName,
          speed: mapData?.vehiclePosition?.speed,
        }
      : null;

  // Build stops for map layers
  const mapStops = (route?.stops ?? [])
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

  // Build vehicle markers
  const vehicleMarkers =
    mapData?.vehiclePosition && mapData.vehiclePublicId
      ? [
          {
            publicId: mapData.vehiclePublicId,
            name: route?.vehicleName ?? 'Vehicle',
            lat: mapData.vehiclePosition.lat,
            lng: mapData.vehiclePosition.lng,
            speed: mapData.vehiclePosition.speed,
            course: mapData.vehiclePosition.course,
          },
        ]
      : [];

  const nonOriginStops = route?.stops.filter((s) => !s.isOrigin) ?? [];
  const deliveredCount = nonOriginStops.filter((s) => s.status === 'DELIVERED').length;
  const totalCount = nonOriginStops.length;
  const nextPendingStop = nonOriginStops.find((s) => s.status === 'PENDING');

  return (
    <div className="flex flex-col h-screen w-full">
      {navOpen && <NavigationSidebar mode="overlay" onClose={() => setNavOpen(false)} />}
      <TopBar compact onMenuClick={() => setNavOpen(true)} />
      <div className="flex-1 relative overflow-hidden">
        <MapCanvas
          ref={mapRef}
          initialCenter={mapData?.origin ?? undefined}
          initialZoom={mapData?.origin ? 13 : 6}
        >
          {/* Route polyline */}
          {route?.polyline && (
            <RoutePolylineLayer
              id={route!.publicId}
              polyline={route!.polyline!}
              color={route!.color}
            />
          )}

          {/* Stop markers */}
          <StopMarkersLayer
            stops={mapStops}
            onStopClick={handleStopClick}
            routeColor={route?.color}
            selectedSequence={selectedStopSequence}
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

          {/* Vehicle marker */}
          <VehicleLayer vehicles={vehicleMarkers} />

          {/* Vehicle trail */}
          <VehicleTrailLayer coordinates={trailCoordinates} />
        </MapCanvas>

        <BottomSheet
          state={sheetState}
          onStateChange={setSheetState}
          title={route?.name ?? 'Route'}
          isLoading={isLoading}
          error={error}
          loadingText="Loading route data..."
        >
          {route && <div className="px-4 pb-4 space-y-3">
            {/* Entity Action Panel */}
            {selection && (
              <EntityActionPanel
                selection={selection}
                userRole={me?.role}
                onClose={clear}
              />
            )}

            {/* Always visible: summary bar + SSE indicator */}
            <div className="flex items-center gap-2">
              <RouteSummaryBar
                status={route.status ?? 'PLANNED'}
                deliveredCount={deliveredCount}
                totalCount={totalCount}
                remainingDistance={metrics?.distanceAfterKm != null ? `${metrics.distanceAfterKm.toFixed(1)} km` : undefined}
                nextEta={nextPendingStop?.etaTime}
              />
              {sseConnected && (
                <span className="flex items-center gap-1 text-[10px] text-emerald-500 flex-shrink-0">
                  <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
                  Live
                </span>
              )}
            </div>

            {/* Medium zone: metrics (visible when enough space) */}
            {contentHeight >= 350 && <RouteMetricsPanel metrics={metrics} />}

            {/* Large zone: vehicle info */}
            {contentHeight >= 450 && <VehicleInfoPanel vehicle={vehicleInfo} />}

            {/* Always visible: stops */}
            <div>
              <div className="text-[10px] text-slate-500 uppercase tracking-wider mb-2">
                Stops ({totalCount})
              </div>
              <StopListPanel
                stops={route.stops}
                selectedSequence={selectedStopSequence}
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
