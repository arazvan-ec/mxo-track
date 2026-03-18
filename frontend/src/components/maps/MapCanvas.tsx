import {
  useRef,
  useImperativeHandle,
  forwardRef,
  type ReactNode,
} from 'react';
import Map, { NavigationControl, type MapRef } from 'react-map-gl/maplibre';
import maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import { Protocol } from 'pmtiles';

// Register PMTiles protocol once
let protocolRegistered = false;
if (!protocolRegistered) {
  const protocol = new Protocol();
  maplibregl.addProtocol('pmtiles', protocol.tile);
  protocolRegistered = true;
}

// Default OSM raster style
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

export interface MapCanvasHandle {
  flyTo(lng: number, lat: number, zoom?: number): void;
  fitBounds(points: Array<{ lat: number; lng: number }>): void;
  getMapRef(): MapRef | null;
}

interface Props {
  children?: ReactNode;
  initialCenter?: { lat: number; lng: number };
  initialZoom?: number;
  showControls?: boolean;
}

export const MapCanvas = forwardRef<MapCanvasHandle, Props>(function MapCanvas(
  {
    children,
    initialCenter = { lat: 40.416, lng: -3.703 },
    initialZoom = 6,
    showControls = true,
  },
  ref,
) {
  const mapRef = useRef<MapRef>(null);

  useImperativeHandle(ref, () => ({
    flyTo(lng, lat, zoom = 15) {
      mapRef.current?.flyTo({ center: [lng, lat], zoom, duration: 1000 });
    },
    fitBounds(points) {
      if (!mapRef.current || points.length === 0) return;
      const bounds = new maplibregl.LngLatBounds();
      points.forEach((p) => bounds.extend([p.lng, p.lat]));
      mapRef.current.fitBounds(bounds, { padding: 80, duration: 1000 });
    },
    getMapRef() {
      return mapRef.current;
    },
  }));

  return (
    <Map
      ref={mapRef}
      mapLib={maplibregl}
      mapStyle={MAP_STYLE}
      initialViewState={{
        latitude: initialCenter.lat,
        longitude: initialCenter.lng,
        zoom: initialZoom,
      }}
      style={{ width: '100%', height: '100%' }}
    >
      {showControls && <NavigationControl position="top-right" />}
      {children}
    </Map>
  );
});
