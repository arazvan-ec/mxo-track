import { layers, DARK, LIGHT, type Flavor } from '@protomaps/basemaps';
import type { StyleSpecification } from 'maplibre-gl';

const TILE_URL = 'https://maps.protomaps.com/tiles/v4/{z}/{x}/{y}.mvt';

const darkFlavor: Flavor = {
  ...DARK,
  background: '#0f172a',
  earth: '#0f172a',
  water: '#1e293b',
  highway: '#475569',
  major: '#334155',
  minor_a: '#1e293b',
  minor_b: '#1e293b',
  link: '#1e293b',
  buildings: '#1a2332',
  park_a: '#0d1f1a',
  park_b: '#0d1f1a',
  industrial: '#131c2a',
};

const lightFlavor: Flavor = {
  ...LIGHT,
  background: '#f1f5f9',
  earth: '#f1f5f9',
  water: '#bfdbfe',
  highway: '#94a3b8',
  major: '#cbd5e1',
  minor_a: '#e2e8f0',
  minor_b: '#e2e8f0',
  link: '#e2e8f0',
  buildings: '#dde3ea',
  park_a: '#bbf7d0',
  park_b: '#bbf7d0',
  industrial: '#e2e8f0',
};

export function createMapStyle(theme: 'light' | 'dark'): StyleSpecification {
  const flavor = theme === 'dark' ? darkFlavor : lightFlavor;
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
