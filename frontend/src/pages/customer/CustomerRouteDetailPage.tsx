import { useRef, useState, useCallback } from 'react';
import { useParams } from 'react-router';
import { useRouteMapData, getRouteFromMapData } from '@/api/hooks/useRouteMapData';
import { MapCanvas, type MapCanvasHandle } from '@/components/maps/MapCanvas';
import { StopListPanel } from '@/components/panels/StopListPanel';
import { VehicleInfoPanel } from '@/components/panels/VehicleInfoPanel';
import { StopMarkersLayer } from '@/components/maps/layers/StopMarkersLayer';
import { RoutePolylineLayer } from '@/components/maps/layers/RoutePolylineLayer';
import { VehicleLayer } from '@/components/maps/layers/VehicleLayer';
import { DualMenuShell } from '@/components/layout/DualMenuShell';
import type { StopData } from '@/api/types';

/**
 * Customer route detail page — shows a single route with stops, ETAs, and vehicle position.
 * Customers see limited data: no optimization metrics, limited vehicle info (name + speed).
 */
export function CustomerRouteDetailPage() {
  const { publicId } = useParams<{ publicId: string }>();
  const mapRef = useRef<MapCanvasHandle>(null);
  const [selectedSequence, setSelectedSequence] = useState<number | null>(null);

  const { mapData, isLoading, error, sseConnected } = useRouteMapData(publicId);
  const { route, stops, vehiclePosition } = getRouteFromMapData(mapData);

  const handleStopClick = useCallback(
    (sequence: number) => {
      setSelectedSequence(sequence);
      const stop = stops.find((s) => s.sequence === sequence);
      if (stop?.lat != null && stop?.lng != null) {
        mapRef.current?.flyTo(stop.lng, stop.lat);
      }
    },
    [stops],
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

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-full bg-slate-900">
        <div className="text-slate-500">Loading route...</div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex items-center justify-center h-full bg-slate-900">
        <div className="text-red-500">Error: {error.message}</div>
      </div>
    );
  }

  if (!route) {
    return (
      <div className="flex items-center justify-center h-full bg-slate-900">
        <div className="text-slate-500">Route not found</div>
      </div>
    );
  }

  const sidebar = (
    <div className="flex flex-col h-full overflow-hidden">
      {/* Header */}
      <div className="p-4 border-b border-slate-800">
        <h1 className="text-lg font-semibold text-white truncate">{route.name}</h1>
        <div className="flex items-center gap-2 mt-1">
          {route.status && (
            <span className="text-xs font-medium text-slate-400 uppercase">{route.status}</span>
          )}
          {sseConnected && (
            <span className="w-2 h-2 rounded-full bg-emerald-500" title="Live updates active" />
          )}
        </div>
      </div>

      {/* Vehicle info (limited: name + speed only for customers) */}
      {route.vehicleName && (
        <div className="px-4 pt-3">
          <VehicleInfoPanel
            vehicle={{
              name: route.vehicleName,
              speed: vehiclePosition?.speed,
            }}
          />
        </div>
      )}

      {/* Stops */}
      <div className="flex-1 overflow-y-auto p-4">
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
    </div>
  );

  return (
    <DualMenuShell dataSidebar={sidebar} dataSidebarWidth="w-80">
      <MapCanvas ref={mapRef}>
        {route.polyline && (
          <RoutePolylineLayer
            id={route.publicId}
            polyline={route.polyline}
            color={route.color}
          />
        )}
        <StopMarkersLayer
          stops={markerStops}
          keyPrefix={`customer-${route.publicId}-`}
          onStopClick={handleStopClick}
          routeColor={route.color}
          selectedSequence={selectedSequence}
        />
        {vehicleMarkers.length > 0 && <VehicleLayer vehicles={vehicleMarkers} />}
      </MapCanvas>
    </DualMenuShell>
  );
}
