import { describe, it, expect } from 'vitest';
import { createMapStyle } from './map-style';

describe('createMapStyle', () => {
  it('returns version 8', () => {
    const style = createMapStyle('light');
    expect(style.version).toBe(8);
  });

  it('returns osm raster source', () => {
    const style = createMapStyle('light');
    const source = style.sources?.['osm'] as { type: string; tiles: string[] };
    expect(source).toBeDefined();
    expect(source.type).toBe('raster');
    expect(source.tiles[0]).toContain('openstreetmap.org');
  });

  it('dark theme has dark background color (#0f172a)', () => {
    const style = createMapStyle('dark');
    const bgLayer = style.layers?.find(
      (l) => 'paint' in l && l.type === 'background',
    ) as { paint?: { 'background-color'?: string } } | undefined;
    expect(bgLayer).toBeDefined();
    expect(bgLayer?.paint?.['background-color']).toBe('#0f172a');
  });

  it('light theme has light background color (#f1f5f9)', () => {
    const style = createMapStyle('light');
    const bgLayer = style.layers?.find(
      (l) => 'paint' in l && l.type === 'background',
    ) as { paint?: { 'background-color'?: string } } | undefined;
    expect(bgLayer).toBeDefined();
    expect(bgLayer?.paint?.['background-color']).toBe('#f1f5f9');
  });

  it('dark theme applies brightness filter to raster tiles', () => {
    const style = createMapStyle('dark');
    const osmLayer = style.layers?.find((l) => l.id === 'osm') as {
      paint?: { 'raster-brightness-max'?: number };
    } | undefined;
    expect(osmLayer?.paint?.['raster-brightness-max']).toBeLessThan(1);
  });

  it('light theme has no raster filter', () => {
    const style = createMapStyle('light');
    const osmLayer = style.layers?.find((l) => l.id === 'osm') as {
      paint?: Record<string, unknown>;
    } | undefined;
    expect(osmLayer?.paint).toEqual({});
  });
});
