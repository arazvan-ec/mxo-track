import { describe, it, expect } from 'vitest';
import { getVehicleColor, SKILL_COLORS, DEFAULT_MARKER_COLOR } from './colors';

describe('getVehicleColor', () => {
  it('returns marker_color when provided', () => {
    expect(getVehicleColor({ marker_color: '#ff0000' })).toBe('#ff0000');
  });

  it('returns skill color for first skill (REFRIGERATED)', () => {
    expect(getVehicleColor({ skills: ['REFRIGERATED'] })).toBe(SKILL_COLORS['REFRIGERATED']);
    expect(getVehicleColor({ skills: ['REFRIGERATED'] })).toBe('#0ea5e9');
  });

  it('returns default when skill is unknown', () => {
    expect(getVehicleColor({ skills: ['UNKNOWN_SKILL'] })).toBe(DEFAULT_MARKER_COLOR);
  });

  it('returns default when no marker_color and no skills', () => {
    expect(getVehicleColor({})).toBe(DEFAULT_MARKER_COLOR);
  });

  it('returns default when skills is empty array', () => {
    expect(getVehicleColor({ skills: [] })).toBe(DEFAULT_MARKER_COLOR);
  });

  it('marker_color takes priority over skills', () => {
    expect(
      getVehicleColor({ marker_color: '#custom', skills: ['REFRIGERATED'] }),
    ).toBe('#custom');
  });
});
