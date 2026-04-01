import { useRef, useEffect, useImperativeHandle, forwardRef } from 'react';
import { MapCanvas, type MapCanvasHandle } from './MapCanvas';
import { VehicleMarker } from './shared/VehicleMarker';
import { StopMarkersLayer } from './layers/StopMarkersLayer';
import { RoutePolylineLayer } from './layers/RoutePolylineLayer';
import { VehicleTrailLayer } from './layers/VehicleTrailLayer';
import { StopPopup } from './shared/StopPopup';
import { VehiclePopup } from '@/components/fleet/VehiclePopup';
import { getVehicleColor } from './shared/colors';
import type { FleetVehicle, FleetRoute } from '@/api/types';

export type FleetMapHandle = MapCanvasHandle;

interface Props {
  vehicles: FleetVehicle[];
  routes: FleetRoute[];
  trailCoordinates?: [number, number][];
  showArrows?: boolean;
  onVehicleClick?: (vehicleId: string) => void;
  onStopClick?: (routePublicId: string, sequence: number) => void;
  selectedRouteId?: string | null;
  selectedStopSequence?: number | null;
}

export const FleetMap = forwardRef<FleetMapHandle, Props>(function FleetMap(
  { vehicles, routes, trailCoordinates, showArrows, onVehicleClick, onStopClick, selectedRouteId, selectedStopSequence },
  ref,
) {
  const canvasRef = useRef<MapCanvasHandle>(null);

  useImperativeHandle(ref, () => ({
    flyTo(lng, lat, zoom, padding) {
      canvasRef.current?.flyTo(lng, lat, zoom, padding);
    },
    fitBounds(points) {
      canvasRef.current?.fitBounds(points);
    },
    getMapRef() {
      return canvasRef.current?.getMapRef() ?? null;
    },
  }));

  // Auto-fit bounds on initial data load
  useEffect(() => {
    if (vehicles.length === 0 && routes.length === 0) return;

    const points: Array<{ lat: number; lng: number }> = [];

    vehicles.forEach((v) => {
      if (v.last_position) {
        points.push({ lat: v.last_position.lat, lng: v.last_position.lng });
      }
    });

    routes.forEach((r) => {
      r.stops.forEach((s) => {
        if (s.lat && s.lng) points.push({ lat: s.lat, lng: s.lng });
      });
    });

    if (points.length > 0) {
      setTimeout(() => canvasRef.current?.fitBounds(points), 100);
    }
  }, [vehicles.length, routes.length]);

  return (
    <MapCanvas ref={canvasRef}>
      {trailCoordinates && trailCoordinates.length > 1 && (
        <VehicleTrailLayer coordinates={trailCoordinates} showArrows={showArrows} />
      )}

      {/* Route polylines — always visible for all routes */}
      {routes.map((route) =>
        route.polyline ? (
          <RoutePolylineLayer
            key={route.publicId}
            id={route.publicId}
            polyline={route.polyline}
            color={route.color}
            showArrows={showArrows}
          />
        ) : null,
      )}

      {/* Stop markers — always visible for all routes */}
      {routes.map((route) => (
        <StopMarkersLayer
          key={`stops-${route.publicId}`}
          stops={route.stops
            .filter((s) => s.lat && s.lng)
            .map((s) => ({
              lat: s.lat,
              lng: s.lng,
              sequence: s.sequence,
              status: s.status,
              address: s.address,
              recipientName: s.recipient,
              shipmentPublicId: s.shipmentPublicId,
            }))}
          keyPrefix={`fleet-${route.publicId}-`}
          onStopClick={(seq) => onStopClick?.(route.publicId, seq)}
          routeColor={route.color}
          selectedSequence={
            selectedRouteId === route.publicId ? selectedStopSequence : null
          }
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
      ))}

      {vehicles.map((vehicle) => {
        if (!vehicle.last_position) return null;
        const route = routes.find((r) => r.vehicleName === vehicle.name);
        return (
          <VehicleMarker
            key={vehicle.public_id}
            lng={vehicle.last_position.lng}
            lat={vehicle.last_position.lat}
            course={vehicle.last_position.course}
            name={vehicle.name}
            speed={vehicle.last_position.speed}
            color={getVehicleColor(vehicle)}
            skills={vehicle.skills}
            onClick={() => onVehicleClick?.(vehicle.public_id)}
            popupContent={
              <VehiclePopup vehicle={vehicle} routeName={route?.name} />
            }
          />
        );
      })}
    </MapCanvas>
  );
});
