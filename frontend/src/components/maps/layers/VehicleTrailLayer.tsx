import { Source, Layer } from 'react-map-gl/maplibre';
import { directionArrowsConfig } from '../shared/directionArrows';

interface Props {
  coordinates: [number, number][]; // [lng, lat][]
  /** Show directional arrows along the trail (default: true) */
  showArrows?: boolean;
}

/**
 * Renders a trail polyline from historical vehicle positions.
 */
export function VehicleTrailLayer({ coordinates, showArrows = true }: Props) {
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
      {showArrows && (
        <Layer
          id="vehicle-trail-arrows"
          type="symbol"
          {...directionArrowsConfig('#3b82f6')}
        />
      )}
    </Source>
  );
}
