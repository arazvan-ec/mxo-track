import { useRef, useEffect, useImperativeHandle, forwardRef } from 'react';
import Map, { type MapRef } from 'react-map-gl/maplibre';
import maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import { Protocol } from 'pmtiles';
import { VehicleMarker } from './shared/VehicleMarker';
import { StopMarker } from './shared/StopMarker';
import { RouteSegments } from './shared/RouteSegments';
import { VehicleTrailLayer } from './shared/VehicleTrailLayer';
import { VehiclePopup } from '@/components/fleet/VehiclePopup';
import { getVehicleColor } from './shared/colors';
import type { FleetVehicle, FleetRoute, FleetStop } from '@/api/types';

// Register PMTiles protocol once
let protocolRegistered = false;
if (!protocolRegistered) {
  const protocol = new Protocol();
  maplibregl.addProtocol('pmtiles', protocol.tile);
  protocolRegistered = true;
}

const MAP_STYLE = {
  version: 8 as const,
  sources: {
    osm: {
      type: 'raster' as const,
      tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
      tileSize: 256,
      attribution: '&copy; OpenStreetMap contributors',
    },
  },
  layers: [{ id: 'osm', type: 'raster' as const, source: 'osm' }],
};

export interface FleetMapHandle {
  flyTo: (lng: number, lat: number, zoom?: number) => void;
  fitBounds: (stops: Array<{ lat: number; lng: number }>) => void;
}

interface Props {
  vehicles: FleetVehicle[];
  routes: FleetRoute[];
  /** Stops to render on the map (from selected vehicle/route) */
  activeStops?: { routeId: string; stops: FleetStop[] } | null;
  /** Trail coordinates [lng, lat][] for selected vehicle */
  trailCoordinates?: [number, number][];
  onVehicleClick?: (vehicleId: string) => void;
}

export const FleetMap = forwardRef<FleetMapHandle, Props>(function FleetMap(
  { vehicles, routes, activeStops, trailCoordinates, onVehicleClick },
  ref,
) {
  const mapRef = useRef<MapRef>(null);

  useImperativeHandle(ref, () => ({
    flyTo(lng, lat, zoom = 15) {
      mapRef.current?.flyTo({ center: [lng, lat], zoom, duration: 1000 });
    },
    fitBounds(stops) {
      if (!mapRef.current || stops.length === 0) return;
      const bounds = new maplibregl.LngLatBounds();
      stops.forEach((s) => bounds.extend([s.lng, s.lat]));
      mapRef.current.fitBounds(bounds, { padding: 80, duration: 1000 });
    },
  }));

  // Auto-fit bounds on initial data load
  useEffect(() => {
    if (!mapRef.current || (vehicles.length === 0 && routes.length === 0)) return;

    const bounds = new maplibregl.LngLatBounds();
    let hasPoints = false;

    vehicles.forEach((v) => {
      if (v.last_position) {
        bounds.extend([v.last_position.lng, v.last_position.lat]);
        hasPoints = true;
      }
    });

    routes.forEach((r) => {
      r.stops.forEach((s) => {
        if (s.lat && s.lng) {
          bounds.extend([s.lng, s.lat]);
          hasPoints = true;
        }
      });
    });

    if (hasPoints) {
      mapRef.current.fitBounds(bounds, { padding: 50, maxZoom: 15 });
    }
  }, [vehicles.length, routes.length]);

  return (
    <Map
      ref={mapRef}
      mapLib={maplibregl}
      mapStyle={MAP_STYLE}
      initialViewState={{ latitude: 40.416, longitude: -3.703, zoom: 6 }}
      style={{ width: '100%', height: '100%' }}
    >
      {/* Vehicle trail (below other layers) */}
      {trailCoordinates && trailCoordinates.length > 1 && (
        <VehicleTrailLayer coordinates={trailCoordinates} />
      )}

      {/* Route segments (stop-to-stop lines) */}
      {activeStops && (
        <RouteSegments routeId={activeStops.routeId} stops={activeStops.stops} />
      )}

      {/* Stop markers (when route/vehicle selected) */}
      {activeStops?.stops.map((stop) =>
        stop.lat && stop.lng ? (
          <StopMarker
            key={`${activeStops.routeId}-${stop.sequence}`}
            lng={stop.lng}
            lat={stop.lat}
            sequence={stop.sequence}
            status={stop.status}
            address={stop.address}
          />
        ) : null,
      )}

      {/* Vehicle markers (always visible) */}
      {vehicles.map((vehicle) => {
        if (!vehicle.last_position) return null;
        const route = routes.find((r) => r.vehicle_name === vehicle.name);
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
    </Map>
  );
});
