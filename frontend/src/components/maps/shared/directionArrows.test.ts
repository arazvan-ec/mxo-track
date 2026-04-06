import { describe, it, expect } from 'vitest';
import { directionArrowsConfig } from './directionArrows';

describe('directionArrowsConfig', () => {
  it('returns layout with correct symbol-placement', () => {
    const config = directionArrowsConfig('#ffffff');
    expect(config.layout['symbol-placement']).toBe('line');
  });

  it('returns paint with given color as text-color', () => {
    const config = directionArrowsConfig('#ff0000');
    expect(config.paint['text-color']).toBe('#ff0000');
  });

  it('different colors produce different paint text-color values', () => {
    const config1 = directionArrowsConfig('#ff0000');
    const config2 = directionArrowsConfig('#00ff00');
    expect(config1.paint['text-color']).not.toBe(config2.paint['text-color']);
  });
});
