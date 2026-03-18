import { Source, Layer } from 'react-map-gl/maplibre';
import { STOP_STATUS_COLORS } from './colors';
import type { FleetStop } from '@/api/types';

interface Props {
  routeId: string;
  stops: FleetStop[];
}

/**
 * Renders stop-to-stop line segments colored by the destination stop's status.
 * This matches the Twig fleet map behavior (no encoded polylines, straight lines).
 */
export function RouteSegments({ routeId, stops }: Props) {
  const validStops = stops.filter((s) => s.lat && s.lng);
  if (validStops.length < 2) return null;

  // Build a GeoJSON FeatureCollection with one LineString per segment
  const features: GeoJSON.Feature<GeoJSON.LineString>[] = [];
  for (let i = 1; i < validStops.length; i++) {
    const from = validStops[i - 1];
    const to = validStops[i];
    const color =
      STOP_STATUS_COLORS[to.status] ?? STOP_STATUS_COLORS.PENDING;

    features.push({
      type: 'Feature',
      properties: { color },
      geometry: {
        type: 'LineString',
        coordinates: [
          [from.lng, from.lat],
          [to.lng, to.lat],
        ],
      },
    });
  }

  const geojson: GeoJSON.FeatureCollection = {
    type: 'FeatureCollection',
    features,
  };

  return (
    <Source id={`route-segments-${routeId}`} type="geojson" data={geojson}>
      <Layer
        id={`route-segments-line-${routeId}`}
        type="line"
        paint={{
          'line-color': ['get', 'color'],
          'line-width': 3,
          'line-opacity': 0.8,
        }}
        layout={{
          'line-cap': 'round',
          'line-join': 'round',
        }}
      />
    </Source>
  );
}
