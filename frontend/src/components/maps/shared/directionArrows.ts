/**
 * Shared MapLibre symbol layer configuration for direction arrows along polylines.
 */
export function directionArrowsConfig(color: string) {
  return {
    layout: {
      'symbol-placement': 'line' as const,
      'symbol-spacing': 100,
      'text-field': '▶',
      'text-size': 12,
      'text-rotation-alignment': 'map' as const,
      'text-allow-overlap': true,
      'text-ignore-placement': true,
      'text-keep-upright': false,
    },
    paint: {
      'text-color': color,
      'text-halo-color': 'rgba(0,0,0,0.7)',
      'text-halo-width': 1,
    },
  };
}
