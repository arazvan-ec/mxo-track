import { Source, Layer } from 'react-map-gl/maplibre';
import { polylineToGeoJSON } from '../shared/polyline';

interface Props {
  id: string;
  polyline: string;
  color: string;
  dashed?: boolean;
  /** Show directional arrows along the route (default: true for solid lines) */
  showArrows?: boolean;
  /** Override line opacity (default: 0.6 for dashed, 0.85 for solid) */
  opacity?: number;
  /** Override line width (default: 3 for dashed, 4 for solid) */
  lineWidth?: number;
}

export function RoutePolylineLayer({ id, polyline, color, dashed, showArrows, opacity, lineWidth }: Props) {
  const geojson = polylineToGeoJSON(polyline);
  const arrows = showArrows ?? !dashed;

  return (
    <Source id={`route-${id}`} type="geojson" data={geojson}>
      <Layer
        id={`route-line-${id}`}
        type="line"
        paint={{
          'line-color': color,
          'line-width': lineWidth ?? (dashed ? 3 : 4),
          'line-opacity': opacity ?? (dashed ? 0.6 : 0.85),
          ...(dashed ? { 'line-dasharray': [4, 3] } : {}),
        }}
        layout={{
          'line-cap': 'round',
          'line-join': 'round',
        }}
      />
      {arrows && (
        <Layer
          id={`route-arrows-${id}`}
          type="symbol"
          layout={{
            'symbol-placement': 'line',
            'symbol-spacing': 100,
            'text-field': '▶',
            'text-size': 12,
            'text-rotation-alignment': 'map',
            'text-allow-overlap': true,
            'text-ignore-placement': true,
            'text-keep-upright': false,
          }}
          paint={{
            'text-color': color,
            'text-halo-color': 'rgba(0,0,0,0.7)',
            'text-halo-width': 1,
          }}
        />
      )}
    </Source>
  );
}
