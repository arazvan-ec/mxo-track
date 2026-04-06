import { describe, it, expect } from 'vitest';
import { decodePolyline, polylineToGeoJSON } from './polyline';

describe('decodePolyline', () => {
  // Google's reference encoded polyline for three points:
  // (38.5, -120.2), (40.7, -120.95), (43.252, -126.453)
  const encoded = '_p~iF~ps|U_ulLnnqC_mqNvxq`@';

  it('decodes known encoded string to expected coordinates', () => {
    const coords = decodePolyline(encoded);
    expect(coords).toHaveLength(3);
    // GeoJSON [lng, lat] order
    expect(coords[0][0]).toBeCloseTo(-120.2, 4);
    expect(coords[0][1]).toBeCloseTo(38.5, 4);
    expect(coords[1][0]).toBeCloseTo(-120.95, 4);
    expect(coords[1][1]).toBeCloseTo(40.7, 4);
    expect(coords[2][0]).toBeCloseTo(-126.453, 3);
    expect(coords[2][1]).toBeCloseTo(43.252, 3);
  });

  it('returns empty array for empty string', () => {
    expect(decodePolyline('')).toEqual([]);
  });

  it('returns [lng, lat] order (GeoJSON convention)', () => {
    const coords = decodePolyline(encoded);
    // First point: lat 38.5, lng -120.2
    // In GeoJSON, index 0 is longitude, index 1 is latitude
    const [lng, lat] = coords[0];
    expect(lng).toBeCloseTo(-120.2, 4);
    expect(lat).toBeCloseTo(38.5, 4);
  });
});

describe('polylineToGeoJSON', () => {
  const encoded = '_p~iF~ps|U_ulLnnqC_mqNvxq`@';

  it('returns valid GeoJSON Feature with LineString geometry', () => {
    const feature = polylineToGeoJSON(encoded);
    expect(feature.type).toBe('Feature');
    expect(feature.properties).toEqual({});
    expect(feature.geometry.type).toBe('LineString');
    expect(Array.isArray(feature.geometry.coordinates)).toBe(true);
    expect(feature.geometry.coordinates.length).toBeGreaterThan(0);
  });

  it('geometry.coordinates matches decodePolyline output', () => {
    const feature = polylineToGeoJSON(encoded);
    const decoded = decodePolyline(encoded);
    expect(feature.geometry.coordinates).toEqual(decoded);
  });
});
