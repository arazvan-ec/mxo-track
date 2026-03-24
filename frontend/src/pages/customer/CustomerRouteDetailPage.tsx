import { useRef, useState, useCallback } from 'react';
import { useParams } from 'react-router';
import { useRouteMapData, getRouteFromMapData } from '@/api/hooks/useRouteMapData';
import { MapCanvas, type MapCanvasHandle } from '@/components/maps/MapCanvas';
import { StopListPanel } from '@/components/panels/StopListPanel';
import { VehicleInfoPanel } from '@/components/panels/VehicleInfoPanel';
import { StopMarkersLayer } from '@/components/maps/layers/StopMarkersLayer';
import { RoutePolylineLayer } from '@/components/maps/layers/RoutePolylineLayer';
import { VehicleLayer } from '@/components/maps/layers/VehicleLayer';
import { BottomSheet, type BottomSheetState } from '@/components/bottom-sheet/BottomSheet';
import { TopBar } from '@/components/layout/TopBar';
import { NavigationSidebar } from '@/components/layout/NavigationSidebar';
import { SHEET_HEIGHTS } from '@/components/bottom-sheet/useBottomSheet';
import type { StopData } from '@/api/types';

/**
 * Customer route detail page — shows a single route with stops, ETAs, and vehicle position.
 * Customers see limited data: no optimization metrics, limited vehicle info (name + speed).
 */
export function CustomerRouteDetailPage() {
  const { publicId } = useParams<{ publicId: string }>();
  const mapRef = useRef<MapCanvasHandle>(null);
  const [selectedSequence, setSelectedSequence] = useState<number | null>(null);
  const [navOpen, setNavOpen] = useState(false);
  const [sheetState, setSheetState] = useState<BottomSheetState>('collapsed');

  const { mapData, isLoading, error, sseConnected } = useRouteMapData(publicId);
  const { route, stops, vehiclePosition } = getRouteFromMapData(mapData);

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
              keyPrefix={`customer-${route.publicId}-`}
              onStopClick={handleStopClick}
              routeColor={route.color}
              selectedSequence={selectedSequence}
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
          {route && <div className="px-4 pb-4 space-y-4">
            {/* Status badge and SSE indicator */}
            <div className="flex items-center gap-2">
              {route.status && (
                <span className="text-xs font-medium text-slate-400 uppercase">{route.status}</span>
              )}
              {sseConnected && (
                <span className="w-2 h-2 rounded-full bg-emerald-500" title="Live updates active" />
              )}
            </div>

            {/* Vehicle info (limited: name + speed only for customers) */}
            {route.vehicleName && (
              <VehicleInfoPanel
                vehicle={{
                  name: route.vehicleName,
                  speed: vehiclePosition?.speed,
                }}
              />
            )}

            {/* Stops */}
            <div>
              <div className="text-[10px] text-slate-500 uppercase tracking-wider mb-2">
                Stops ({stops.filter((s) => !s.isOrigin).length})
              </div>
              <StopListPanel
                stops={stops}
                selectedSequence={selectedSequence}
                onStopClick={handleStopClick}
                showEta
              />
            </div>
          </div>}
        </BottomSheet>
      </div>
    </div>
  );
}
