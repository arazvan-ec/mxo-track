import { layers, DARK, type Flavor } from '@protomaps/basemaps';
import type { StyleSpecification } from 'maplibre-gl';

// Protomaps CDN — free for development. To self-host: replace URL with
// pmtiles://https://your-bucket.s3.region.amazonaws.com/spain.pmtiles
const TILE_URL = 'https://maps.protomaps.com/tiles/v4/{z}/{x}/{y}.mvt';

/**
 * Dark vector tile style customized for logistics UI (slate-900 palette).
 * Uses Protomaps basemaps with dark flavor + road emphasis.
 */
export function createDarkStyle(): StyleSpecification {
  const flavor: Flavor = {
    ...DARK,
    // Match our slate-900/slate-800 UI palette
    background: '#0f172a',      // slate-900
    earth: '#0f172a',           // slate-900
    water: '#1e293b',           // slate-800
    // Emphasize roads — our core business is logistics
    highway: '#475569',         // slate-600 (most visible)
    major: '#334155',           // slate-700
    minor_a: '#1e293b',         // slate-800
    minor_b: '#1e293b',         // slate-800
    link: '#1e293b',            // slate-800
    // Subtle buildings and land use
    buildings: '#1a2332',
    park_a: '#0d1f1a',
    park_b: '#0d1f1a',
    industrial: '#131c2a',
  };

  const mapLayers = layers('protomaps', flavor, { lang: 'es' });

  return {
    version: 8,
    glyphs: 'https://cdn.protomaps.com/fonts/pbf/{fontstack}/{range}.pbf',
    sources: {
      protomaps: {
        type: 'vector',
        tiles: [TILE_URL],
        maxzoom: 15,
        attribution:
          '&copy; <a href="https://protomaps.com">Protomaps</a> &copy; <a href="https://openstreetmap.org">OpenStreetMap</a>',
      },
    },
    layers: mapLayers,
  };
}

/** Fallback raster style if vector tiles are unavailable */
export const FALLBACK_RASTER_STYLE: StyleSpecification = {
  version: 8,
  sources: {
    osm: {
      type: 'raster',
      tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
      tileSize: 256,
      attribution: '&copy; OpenStreetMap contributors',
    },
  },
  layers: [{ id: 'osm', type: 'raster', source: 'osm' }],
};
