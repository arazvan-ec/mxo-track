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

import { SHEET_HEIGHTS } from '@/components/bottom-sheet/useBottomSheet';
import type { StopData } from '@/api/types';

/**
 * Customer route detail page — shows a single route with stops, ETAs, and vehicle position.
 * Customers see limited data: no optimization metrics, limited vehicle info (name + speed).
 */
export function CustomerRouteDetailPage() {
  const { publicId } = useParams<{ publicId: string }>();
  const mapRef = useRef<MapCanvasHandle>(null);
  const hasFittedRef = useRef(false);
  const [sheetState, setSheetState] = useState<BottomSheetState>('collapsed');
  const { data: me } = useMe();
  const { selection, selectStop, clear } = useMapSelection();
  const { layout } = usePageLayout('customer_tracking');

  const { mapData, isLoading, error, sseConnected } = useRouteMapData(publicId);
  const { route, stops, vehiclePosition } = getRouteFromMapData(mapData);

  const selectedStopSequence =
    selection?.type === 'stop'
      ? (selection.data as { sequence: number }).sequence
      : null;

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

  // Fit map to route bounds on first load
  useEffect(() => {
    if (markerStops.length > 0 && !hasFittedRef.current) {
      hasFittedRef.current = true;
      setTimeout(() => mapRef.current?.fitBounds(markerStops), 200);
    }
  }, [markerStops.length]);

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

  const nonOriginStops = stops.filter((s) => !s.isOrigin);
  const deliveredCount = nonOriginStops.filter((s) => s.status === 'DELIVERED').length;
  const totalCount = nonOriginStops.length;
  const nextPendingStop = nonOriginStops.find((s) => s.status === 'PENDING');

  // Widget system data
  const pageData = useMemo(
    () => ({
      vehicleInfo: {
        name: route?.vehicleName,
        speed: vehiclePosition?.speed,
      },
      stops,
      selectedSequence: selectedStopSequence,
      onStopClick: handleStopClick,
      showEta: true,
    }),
    [route?.vehicleName, vehiclePosition?.speed, stops, selectedStopSequence, handleStopClick],
  );

  return (
    <>
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
              keyPrefix={`customer-${route.publicId}-`}
              onStopClick={handleStopClick}
              routeColor={route.color}
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
            {/* Entity Action Panel — outside widget system (selection-driven) */}
            {selection && (
              <div className="px-4">
                <EntityActionPanel
                  selection={selection}
                  userRole={me?.role}
                  onClose={clear}
                />
              </div>
            )}

            {/* Always visible: summary bar + SSE indicator */}
            <div className="px-4 flex items-center gap-2">
              <RouteSummaryBar
                status={route.status ?? ''}
                deliveredCount={deliveredCount}
                totalCount={totalCount}
                nextEta={nextPendingStop?.etaTime}
              />
              {sseConnected && (
                <span className="w-2 h-2 rounded-full bg-emerald-500 flex-shrink-0" title="Live updates active" />
              )}
            </div>

            <WidgetRenderer layout={layout} sheetState={sheetState} pageData={pageData} />
          </div>}
        </BottomSheet>
    </>
  );
}
