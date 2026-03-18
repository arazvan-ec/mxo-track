import { useCallback, useState } from 'react';
import { useMercure } from './useMercure';
import type { VehiclePosition, MapUpdate } from '../types';

interface MapSubscriptionConfig {
  /** Vehicle public IDs to subscribe for position updates */
  vehicleIds?: string[];
  /** Route public IDs to subscribe for route updates */
  routeIds?: string[];
  enabled?: boolean;
}

interface MapSubscriptionResult {
  /** Live vehicle positions, keyed by vehiclePublicId */
  positions: Map<string, VehiclePosition>;
  /** Live route updates, keyed by routePublicId */
  routeUpdates: Map<string, MapUpdate>;
  /** Whether SSE connection is active (at least one message received) */
  connected: boolean;
}

/**
 * Unified hook for subscribing to map-related Mercure topics.
 * Combines vehicle positions + route updates into a single subscription.
 */
export function useMapSubscription(config: MapSubscriptionConfig): MapSubscriptionResult {
  const { vehicleIds = [], routeIds = [], enabled = true } = config;

  const [positions, setPositions] = useState<Map<string, VehiclePosition>>(new Map());
  const [routeUpdates, setRouteUpdates] = useState<Map<string, MapUpdate>>(new Map());
  const [connected, setConnected] = useState(false);

  // Build all topics
  const topics = [
    ...vehicleIds.map((id) => `/map/vehicles/${id}/position`),
    ...routeIds.map((id) => `/map/routes/${id}/updates`),
  ];

  const handleMessage = useCallback((data: Record<string, unknown>) => {
    setConnected(true);

    // Vehicle position update
    const vid = data.vehiclePublicId as string | undefined;
    if (vid && typeof data.lat === 'number' && typeof data.lng === 'number') {
      const position: VehiclePosition = {
        vehiclePublicId: vid,
        lat: data.lat as number,
        lng: data.lng as number,
        speed: data.speed as number | undefined,
        course: data.course as number | undefined,
        deviceTime: (data.deviceTime as string) ?? '',
      };
      setPositions((prev) => {
        const next = new Map(prev);
        next.set(vid, position);
        return next;
      });
      return;
    }

    // Route update (MapUpdate envelope)
    const routePublicId = data.routePublicId as string | undefined;
    const type = data.type as string | undefined;
    if (routePublicId && type) {
      const update: MapUpdate = {
        type: type as MapUpdate['type'],
        routePublicId,
        data: (data.data as Record<string, unknown>) ?? {},
        occurredAt: (data.occurredAt as string) ?? '',
      };
      setRouteUpdates((prev) => {
        const next = new Map(prev);
        next.set(routePublicId, update);
        return next;
      });
    }
  }, []);

  useMercure<Record<string, unknown>>(
    topics,
    handleMessage,
    enabled && topics.length > 0,
  );

  return { positions, routeUpdates, connected };
}
