import type { StyleSpecification } from 'maplibre-gl';

/**
 * Creates a raster OSM tile style with optional dark-mode filter.
 *
 * Protomaps vector tiles were previously used but the endpoint
 * (maps.protomaps.com) returns 404. OSM raster tiles are used until
 * a valid Protomaps API key is configured.
 */
export function createMapStyle(theme: 'light' | 'dark'): StyleSpecification {
  const isDark = theme === 'dark';

  return {
    version: 8,
    sources: {
      osm: {
        type: 'raster',
        tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
        tileSize: 256,
        attribution: '&copy; OpenStreetMap contributors',
      },
    },
    layers: [
      // Background color for areas not covered by tiles
      {
        id: 'background',
        type: 'background',
        paint: {
          'background-color': isDark ? '#0f172a' : '#f1f5f9',
        },
      },
      {
        id: 'osm',
        type: 'raster',
        source: 'osm',
        paint: isDark
          ? {
              // Invert + desaturate for dark mode
              'raster-brightness-max': 0.45,
              'raster-saturation': -0.4,
              'raster-contrast': 0.2,
            }
          : {},
      },
    ],
  };
}
