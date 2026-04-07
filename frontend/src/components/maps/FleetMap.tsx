import { useRef, useEffect, useImperativeHandle, forwardRef } from 'react';
import { MapCanvas, type MapCanvasHandle } from './MapCanvas';
import { VehicleMarker } from './shared/VehicleMarker';
import { RouteMapLayers } from './layers/RouteMapLayers';
import { VehicleTrailLayer } from './layers/VehicleTrailLayer';
import { VehiclePopup } from '@/components/fleet/VehiclePopup';
import { getVehicleColor } from './shared/colors';
import type { FleetVehicle, FleetRoute } from '@/api/types';

export type FleetMapHandle = MapCanvasHandle;

interface Props {
  vehicles: FleetVehicle[];
  routes: FleetRoute[];
  trailCoordinates?: [number, number][];
  onVehicleClick?: (vehicleId: string) => void;
  onStopClick?: (routePublicId: string, sequence: number) => void;
  selectedRouteId?: string | null;
  selectedStopSequence?: number | null;
}

export const FleetMap = forwardRef<FleetMapHandle, Props>(function FleetMap(
  { vehicles, routes, trailCoordinates, onVehicleClick, onStopClick, selectedRouteId, selectedStopSequence },
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
        <VehicleTrailLayer coordinates={trailCoordinates} />
      )}

      <RouteMapLayers
        routes={routes}
        onStopClick={onStopClick}
        selectedRouteId={selectedRouteId}
        selectedStopSequence={selectedStopSequence}
        keyPrefix="fleet-"
      />

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
