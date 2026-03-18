import { Source, Layer } from 'react-map-gl/maplibre';
import { polylineToGeoJSON } from './polyline';

interface Props {
  id: string;
  polyline: string;
  color: string;
}

export function RouteLayer({ id, polyline, color }: Props) {
  const geojson = polylineToGeoJSON(polyline);

  return (
    <Source id={`route-${id}`} type="geojson" data={geojson}>
      <Layer
        id={`route-line-${id}`}
        type="line"
        paint={{
          'line-color': color,
          'line-width': 4,
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
