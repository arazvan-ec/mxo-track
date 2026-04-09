import {
  useRef,
  useImperativeHandle,
  useMemo,
  forwardRef,
  type ReactNode,
} from 'react';
import Map, { NavigationControl, type MapRef } from 'react-map-gl/maplibre';
import maplibregl from 'maplibre-gl';
import type { MapLayerMouseEvent } from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import { createMapStyle } from './styles/map-style';
import { useTheme } from '@/context/ThemeProvider';

export interface MapCanvasHandle {
  flyTo(lng: number, lat: number, zoom?: number, padding?: maplibregl.PaddingOptions): void;
  fitBounds(
    points: Array<{ lat: number; lng: number }>,
    options?: { padding?: number | { top: number; right: number; bottom: number; left: number } },
  ): void;
  getMapRef(): MapRef | null;
}

interface Props {
  children?: ReactNode;
  initialCenter?: { lat: number; lng: number };
  initialZoom?: number;
  showControls?: boolean;
  interactiveLayerIds?: string[];
  onClick?: (e: MapLayerMouseEvent) => void;
  cursor?: string;
}

export const MapCanvas = forwardRef<MapCanvasHandle, Props>(function MapCanvas(
  {
    children,
    initialCenter = { lat: 40.416, lng: -3.703 },
    initialZoom = 6,
    showControls = true,
    interactiveLayerIds,
    onClick,
    cursor,
  },
  ref,
) {
  const mapRef = useRef<MapRef>(null);
  const { resolved: theme } = useTheme();
  const mapStyle = useMemo(() => createMapStyle(theme), [theme]);

  useImperativeHandle(ref, () => ({
    flyTo(lng, lat, zoom = 15, padding) {
      mapRef.current?.flyTo({ center: [lng, lat], zoom, duration: 1000, padding });
    },
    fitBounds(points, options) {
      if (!mapRef.current || points.length === 0) return;
      const bounds = new maplibregl.LngLatBounds();
      points.forEach((p) => bounds.extend([p.lng, p.lat]));
      mapRef.current.fitBounds(bounds, {
        padding: options?.padding ?? 80,
        duration: 1000,
      });
    },
    getMapRef() {
      return mapRef.current;
    },
  }));

  return (
    <Map
      ref={mapRef}
      mapLib={maplibregl}
      mapStyle={mapStyle}
      canvasContextAttributes={{ preserveDrawingBuffer: true }}
      initialViewState={{
        latitude: initialCenter.lat,
        longitude: initialCenter.lng,
        zoom: initialZoom,
      }}
      style={{ width: '100%', height: '100%' }}
      interactiveLayerIds={interactiveLayerIds}
      onClick={onClick}
      cursor={cursor}
    >
      {showControls && <NavigationControl position="top-right" />}
      {children}
    </Map>
  );
});
