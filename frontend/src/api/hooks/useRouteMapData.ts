import { useCallback, useEffect, useState } from 'react';
import { api } from '../client';
import { useMapSubscription } from './useMapSubscription';
import type { MapData, StopData } from '../types';

interface UseRouteMapDataResult {
  mapData: MapData | null;
  isLoading: boolean;
  error: Error | null;
  sseConnected: boolean;
}

/**
 * Fetches route map data from /api/map/route/{publicId} and subscribes
 * to live Mercure updates for the route and its vehicle.
 */
export function useRouteMapData(publicId: string | undefined): UseRouteMapDataResult {
  const [mapData, setMapData] = useState<MapData | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<Error | null>(null);

  // Fetch initial data
  useEffect(() => {
    if (!publicId) return;

    let cancelled = false;
    setIsLoading(true);
    setError(null);

    api
      .get<MapData>(`/api/map/route/${publicId}`)
      .then((data) => {
        if (!cancelled) {
          setMapData(data);
          setIsLoading(false);
        }
      })
      .catch((err: unknown) => {
        if (!cancelled) {
          setError(err instanceof Error ? err : new Error(String(err)));
          setIsLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [publicId]);

  // Derive subscription IDs from loaded data
  const routeIds = mapData?.routes.map((r) => r.publicId) ?? [];
  const vehicleIds = mapData?.vehiclePublicId ? [mapData.vehiclePublicId] : [];

  const { positions, routeUpdates, connected } = useMapSubscription({
    vehicleIds,
    routeIds,
    enabled: !!mapData,
  });

  // Apply live vehicle position to mapData
  const enrichedMapData = useEnrichedMapData(mapData, positions);

  // Apply route updates (stop status changes, ETAs)
  const finalMapData = useApplyRouteUpdates(enrichedMapData, routeUpdates);

  return {
    mapData: finalMapData,
    isLoading,
    error,
    sseConnected: connected,
  };
}

/**
 * Enriches mapData with live vehicle positions from Mercure.
 */
function useEnrichedMapData(
  mapData: MapData | null,
  positions: Map<string, { lat: number; lng: number; speed?: number; course?: number }>,
): MapData | null {
  return mapData
    ? (() => {
        if (!mapData.vehiclePublicId) return mapData;
        const pos = positions.get(mapData.vehiclePublicId);
        if (!pos) return mapData;
        return {
          ...mapData,
          vehiclePosition: {
            lat: pos.lat,
            lng: pos.lng,
            speed: pos.speed,
            course: pos.course,
          },
        };
      })()
    : null;
}

/**
 * Applies route update events to mapData (e.g. stop delivered, ETA changed).
 */
function useApplyRouteUpdates(
  mapData: MapData | null,
  _routeUpdates: Map<string, unknown>,
): MapData | null {
  // For now, route updates trigger a re-render signal but the actual
  // stop data refresh would come from a full re-fetch or SSE payload.
  // This is a placeholder for incremental update logic.
  const applyUpdates = useCallback(
    (data: MapData | null): MapData | null => {
      if (!data) return null;
      // Future: merge _routeUpdates into stops (status, ETA, etc.)
      return data;
    },
    [_routeUpdates],
  );

  return applyUpdates(mapData);
}

/**
 * Helper to extract the first (and usually only) route from MapData.
 */
export function getRouteFromMapData(mapData: MapData | null): {
  route: MapData['routes'][0] | null;
  stops: StopData[];
  vehiclePosition: MapData['vehiclePosition'];
} {
  const route = mapData?.routes[0] ?? null;
  return {
    route,
    stops: route?.stops ?? [],
    vehiclePosition: mapData?.vehiclePosition,
  };
}
