import { useRef, useEffect } from 'react';
import Map, { type MapRef } from 'react-map-gl/maplibre';
import maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import { Protocol } from 'pmtiles';
import { VehicleMarker } from './shared/VehicleMarker';
import { StopMarker } from './shared/StopMarker';
import type { FleetVehicle, FleetRoute } from '@/api/types';

// Register PMTiles protocol once
let protocolRegistered = false;
if (!protocolRegistered) {
  const protocol = new Protocol();
  maplibregl.addProtocol('pmtiles', protocol.tile);
  protocolRegistered = true;
}

// Default OSM raster style (fallback until PMTiles is configured)
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
  layers: [
    {
      id: 'osm',
      type: 'raster' as const,
      source: 'osm',
    },
  ],
};

interface Props {
  vehicles: FleetVehicle[];
  routes: FleetRoute[];
  onVehicleClick?: (vehicleId: string) => void;
  onRouteClick?: (routeId: string) => void;
}

export function FleetMap({ vehicles, routes, onVehicleClick, onRouteClick }: Props) {
  const mapRef = useRef<MapRef>(null);

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
      initialViewState={{
        latitude: 40.416,
        longitude: -3.703,
        zoom: 6,
      }}
      style={{ width: '100%', height: '100%' }}
    >
      {/* Stop markers */}
      {routes.flatMap((route) =>
        route.stops.map((stop) =>
          stop.lat && stop.lng ? (
            <StopMarker
              key={`${route.public_id}-${stop.sequence}`}
              lng={stop.lng}
              lat={stop.lat}
              sequence={stop.sequence}
              status={stop.status}
              address={stop.address}
              onClick={() => onRouteClick?.(route.public_id)}
            />
          ) : null,
        ),
      )}

      {/* Vehicle markers */}
      {vehicles.map((vehicle) =>
        vehicle.last_position ? (
          <VehicleMarker
            key={vehicle.public_id}
            lng={vehicle.last_position.lng}
            lat={vehicle.last_position.lat}
            course={vehicle.last_position.course}
            name={vehicle.name}
            color={vehicle.marker_color}
            onClick={() => onVehicleClick?.(vehicle.public_id)}
          />
        ) : null,
      )}
    </Map>
  );
}
