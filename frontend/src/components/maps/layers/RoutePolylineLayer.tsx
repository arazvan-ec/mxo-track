import { Source, Layer } from 'react-map-gl/maplibre';
import { polylineToGeoJSON } from '../shared/polyline';

interface Props {
  id: string;
  polyline: string;
  color: string;
  dashed?: boolean;
}

export function RoutePolylineLayer({ id, polyline, color, dashed }: Props) {
  const geojson = polylineToGeoJSON(polyline);

  return (
    <Source id={`route-${id}`} type="geojson" data={geojson}>
      <Layer
        id={`route-line-${id}`}
        type="line"
        paint={{
          'line-color': color,
          'line-width': dashed ? 3 : 4,
          'line-opacity': dashed ? 0.6 : 0.8,
          ...(dashed ? { 'line-dasharray': [4, 3] } : {}),
        }}
        layout={{
          'line-cap': 'round',
          'line-join': 'round',
        }}
      />
    </Source>
  );
}
