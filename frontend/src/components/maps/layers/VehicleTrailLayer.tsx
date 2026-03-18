import { Source, Layer } from 'react-map-gl/maplibre';

interface Props {
  coordinates: [number, number][]; // [lng, lat][]
}

/**
 * Renders a trail polyline from historical vehicle positions.
 */
export function VehicleTrailLayer({ coordinates }: Props) {
  if (coordinates.length < 2) return null;

  const geojson: GeoJSON.Feature<GeoJSON.LineString> = {
    type: 'Feature',
    properties: {},
    geometry: {
      type: 'LineString',
      coordinates,
    },
  };

  return (
    <Source id="vehicle-trail" type="geojson" data={geojson}>
      <Layer
        id="vehicle-trail-line"
        type="line"
        paint={{
          'line-color': '#3b82f6',
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
