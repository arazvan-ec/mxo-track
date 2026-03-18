import { Source, Layer } from 'react-map-gl/maplibre';

interface StopPoint {
  lat: number;
  lng: number;
  sequence: number;
  address: string;
  recipient?: string;
}

interface Props {
  originalStops: StopPoint[];
  optimizedStops: StopPoint[];
  routeId: string;
  origin?: { lat: number; lng: number };
  originalColor?: string;
  optimizedColor?: string;
}

function stopsToLineString(
  stops: StopPoint[],
  origin?: { lat: number; lng: number },
): GeoJSON.Feature<GeoJSON.LineString> {
  const coords: [number, number][] = [];
  if (origin) {
    coords.push([origin.lng, origin.lat]);
  }
  for (const stop of stops) {
    if (stop.lat && stop.lng) {
      coords.push([stop.lng, stop.lat]);
    }
  }
  if (origin && coords.length > 1) {
    coords.push([origin.lng, origin.lat]);
  }
  return {
    type: 'Feature',
    properties: {},
    geometry: {
      type: 'LineString',
      coordinates: coords,
    },
  };
}

/**
 * Renders two route polylines for comparison:
 * - Original route as a dashed line
 * - Optimized route as a solid line
 */
export function ComparisonLayer({
  originalStops,
  optimizedStops,
  routeId,
  origin,
  originalColor = '#EF4444',
  optimizedColor = '#3B82F6',
}: Props) {
  const originalGeoJson = stopsToLineString(originalStops, origin);
  const optimizedGeoJson = stopsToLineString(optimizedStops, origin);

  return (
    <>
      {/* Original route — dashed */}
      <Source
        id={`comparison-original-${routeId}`}
        type="geojson"
        data={originalGeoJson}
      >
        <Layer
          id={`comparison-original-line-${routeId}`}
          type="line"
          paint={{
            'line-color': originalColor,
            'line-width': 3,
            'line-opacity': 0.6,
            'line-dasharray': [4, 3],
          }}
          layout={{
            'line-cap': 'round',
            'line-join': 'round',
          }}
        />
      </Source>

      {/* Optimized route — solid */}
      <Source
        id={`comparison-optimized-${routeId}`}
        type="geojson"
        data={optimizedGeoJson}
      >
        <Layer
          id={`comparison-optimized-line-${routeId}`}
          type="line"
          paint={{
            'line-color': optimizedColor,
            'line-width': 4,
            'line-opacity': 0.8,
          }}
          layout={{
            'line-cap': 'round',
            'line-join': 'round',
          }}
        />
      </Source>
    </>
  );
}

export type { StopPoint };
