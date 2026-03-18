import { Source, Layer } from 'react-map-gl/maplibre';
import { useMemo } from 'react';

export interface ExceptionData {
  lat: number;
  lng: number;
  address: string;
  type: string;
  routeName: string;
  date: string | null;
}

interface Props {
  exceptions: ExceptionData[];
  mode: 'heatmap' | 'points';
}

/**
 * Native WebGL exception visualization with heatmap/points toggle.
 * Heatmap shows density at low zoom; points show individual exceptions.
 * Auto-falls back to points if <20 exceptions (heatmap looks sparse).
 */
export function ExceptionHeatmapLayer({ exceptions, mode }: Props) {
  const geojson = useMemo(
    () => ({
      type: 'FeatureCollection' as const,
      features: exceptions.map((ex, i) => ({
        type: 'Feature' as const,
        id: i,
        geometry: {
          type: 'Point' as const,
          coordinates: [ex.lng, ex.lat] as [number, number],
        },
        properties: {
          type: ex.type,
          address: ex.address,
          routeName: ex.routeName,
          date: ex.date,
        },
      })),
    }),
    [exceptions],
  );

  // Auto-select: heatmap needs enough data to look meaningful
  const effectiveMode = exceptions.length < 20 ? 'points' : mode;

  return (
    <Source id="exceptions-source" type="geojson" data={geojson}>
      {/* Heatmap layer */}
      <Layer
        id="exceptions-heatmap"
        type="heatmap"
        layout={{
          visibility: effectiveMode === 'heatmap' ? 'visible' : 'none',
        }}
        paint={{
          'heatmap-weight': 1,
          'heatmap-intensity': [
            'interpolate',
            ['linear'],
            ['zoom'],
            0,
            1,
            12,
            3,
          ],
          'heatmap-color': [
            'interpolate',
            ['linear'],
            ['heatmap-density'],
            0,
            'rgba(0,0,0,0)',
            0.2,
            'rgba(59,130,246,0.5)', // blue-500
            0.4,
            'rgba(139,92,246,0.6)', // violet-500
            0.6,
            'rgba(245,158,11,0.7)', // amber-500
            0.8,
            'rgba(239,68,68,0.8)', // red-500
            1,
            'rgba(239,68,68,1)',
          ],
          'heatmap-radius': [
            'interpolate',
            ['linear'],
            ['zoom'],
            0,
            15,
            12,
            30,
            16,
            50,
          ],
          'heatmap-opacity': [
            'interpolate',
            ['linear'],
            ['zoom'],
            12,
            1,
            16,
            0.6,
          ],
        }}
      />

      {/* Point circles */}
      <Layer
        id="exceptions-points"
        type="circle"
        layout={{
          visibility: effectiveMode === 'points' ? 'visible' : 'none',
        }}
        paint={{
          'circle-color': 'rgba(239, 68, 68, 0.85)',
          'circle-radius': [
            'interpolate',
            ['linear'],
            ['zoom'],
            6,
            4,
            12,
            7,
            16,
            10,
          ],
          'circle-stroke-width': 2,
          'circle-stroke-color': 'rgba(255,255,255,0.6)',
        }}
      />
    </Source>
  );
}
