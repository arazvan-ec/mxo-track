import { describe, it, expect } from 'vitest';
import { getRouteFromMapData } from './useRouteMapData';
import type { MapData, StopData } from '../types';

function makeStop(overrides: Partial<StopData> = {}): StopData {
  return {
    sequence: 1,
    address: '123 Main St',
    status: 'pending',
    isOrigin: false,
    ...overrides,
  };
}

function makeMapData(overrides: Partial<MapData> = {}): MapData {
  return {
    routes: [
      {
        publicId: 'route-1',
        name: 'Route 1',
        color: '#ff0000',
        stops: [makeStop({ sequence: 1 }), makeStop({ sequence: 2, address: '456 Oak Ave' })],
      },
    ],
    ...overrides,
  };
}

describe('getRouteFromMapData', () => {
  it('returns null route when mapData is null', () => {
    const result = getRouteFromMapData(null);
    expect(result.route).toBeNull();
  });

  it('returns empty stops when mapData is null', () => {
    const result = getRouteFromMapData(null);
    expect(result.stops).toEqual([]);
  });

  it('returns first route from mapData', () => {
    const mapData = makeMapData();
    const result = getRouteFromMapData(mapData);

    expect(result.route).not.toBeNull();
    expect(result.route!.publicId).toBe('route-1');
    expect(result.route!.name).toBe('Route 1');
  });

  it('returns stops from first route', () => {
    const mapData = makeMapData();
    const result = getRouteFromMapData(mapData);

    expect(result.stops).toHaveLength(2);
    expect(result.stops[0].sequence).toBe(1);
    expect(result.stops[1].address).toBe('456 Oak Ave');
  });

  it('returns vehiclePosition from mapData', () => {
    const mapData = makeMapData({
      vehiclePosition: { lat: 19.4326, lng: -99.1332, speed: 45, course: 180 },
    });
    const result = getRouteFromMapData(mapData);

    expect(result.vehiclePosition).toEqual({
      lat: 19.4326,
      lng: -99.1332,
      speed: 45,
      course: 180,
    });
  });

  it('handles mapData with empty routes array', () => {
    const mapData = makeMapData({ routes: [] });
    const result = getRouteFromMapData(mapData);

    expect(result.route).toBeNull();
    expect(result.stops).toEqual([]);
  });
});
