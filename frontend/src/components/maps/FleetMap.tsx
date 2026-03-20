import { useRef, useEffect, useImperativeHandle, forwardRef } from 'react';
import { MapCanvas, type MapCanvasHandle } from './MapCanvas';
import { VehicleMarker } from './shared/VehicleMarker';
import { StopMarker } from './shared/StopMarker';
import { RoutePolylineLayer } from './layers/RoutePolylineLayer';
import { VehicleTrailLayer } from './layers/VehicleTrailLayer';
import { VehiclePopup } from '@/components/fleet/VehiclePopup';
import { getVehicleColor } from './shared/colors';
import type { FleetVehicle, FleetRoute, FleetStop } from '@/api/types';

export type FleetMapHandle = MapCanvasHandle;

interface Props {
  vehicles: FleetVehicle[];
  routes: FleetRoute[];
  activeStops?: { routeId: string; stops: FleetStop[]; polyline?: string; color?: string } | null;
  trailCoordinates?: [number, number][];
  onVehicleClick?: (vehicleId: string) => void;
}

export const FleetMap = forwardRef<FleetMapHandle, Props>(function FleetMap(
  { vehicles, routes, activeStops, trailCoordinates, onVehicleClick },
  ref,
) {
  const canvasRef = useRef<MapCanvasHandle>(null);

  useImperativeHandle(ref, () => ({
    flyTo(lng, lat, zoom) {
      canvasRef.current?.flyTo(lng, lat, zoom);
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
        <VehicleTrailLayer coordinates={trailCoordinates} />
      )}

      {activeStops?.polyline && (
        <RoutePolylineLayer
          id={activeStops.routeId}
          polyline={activeStops.polyline}
          color={activeStops.color ?? '#3B82F6'}
        />
      )}

      {activeStops?.stops.map((stop) =>
        stop.lat && stop.lng ? (
          <StopMarker
            key={`${activeStops.routeId}-${stop.sequence}`}
            lng={stop.lng}
            lat={stop.lat}
            sequence={stop.sequence}
            status={stop.status}
            address={stop.address}
            routeColor={activeStops.color ?? '#3B82F6'}
          />
        ) : null,
      )}

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
