import { describe, it, expect } from 'vitest';
import { createMapStyle } from './map-style';

describe('createMapStyle', () => {
  it('returns version 8', () => {
    const style = createMapStyle('light');
    expect(style.version).toBe(8);
  });

  it('returns protomaps source with vector type', () => {
    const style = createMapStyle('light');
    const source = style.sources?.['protomaps'] as { type: string };
    expect(source).toBeDefined();
    expect(source.type).toBe('vector');
  });

  it('dark theme has dark background color (#0f172a)', () => {
    const style = createMapStyle('dark');
    const bgLayer = style.layers?.find(
      (l) => 'paint' in l && l.type === 'background',
    ) as { paint?: { 'background-color'?: string } } | undefined;
    // The background is set via the flavor; check that the layer exists
    // and uses the dark background color
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

  it('returns glyphs URL', () => {
    const style = createMapStyle('light');
    expect(style.glyphs).toBe(
      'https://cdn.protomaps.com/fonts/pbf/{fontstack}/{range}.pbf',
    );
  });
});
