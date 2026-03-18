import { useCallback, useState } from 'react';
import { useMercure } from './useMercure';
import type { MapUpdate, FleetRoute, FleetStop } from '../types';

/**
 * Listens to Mercure route update topics and returns a map of
 * routePublicId -> updated route data (stops, status, counts).
 */
export function useMercureRouteUpdates(routePublicIds: string[]) {
  const [updates, setUpdates] = useState<Map<string, Partial<FleetRoute>>>(
    new Map(),
  );

  const topics = routePublicIds.map(
    (id) => `/map/routes/${id}/updates`,
  );

  const handleMessage = useCallback((data: MapUpdate) => {
    if (!data.routePublicId || !data.data) return;

    const routeData = data.data as Record<string, unknown>;

    // For route_snapshot, extract full stop data
    if (data.type === 'route_snapshot' && routeData.routes) {
      const routes = routeData.routes as Array<Record<string, unknown>>;
      const updatedRoute = routes[0];
      if (!updatedRoute) return;

      const stops = ((updatedRoute.stops as Array<Record<string, unknown>>) ?? []).map(
        (s): FleetStop => ({
          lat: s.lat as number,
          lng: s.lng as number,
          address: (s.address as string) || '',
          sequence: s.sequence as number,
          status: (s.status as string) || 'PENDING',
          recipient: s.recipientName as string | undefined,
        }),
      );

      const nonOriginStops = stops;

      setUpdates((prev) => {
        const next = new Map(prev);
        next.set(data.routePublicId, {
          stops,
          total_stops: nonOriginStops.length,
          delivered_stops: nonOriginStops.filter((s) => s.status === 'DELIVERED').length,
          status: (updatedRoute.status as string) || undefined,
        });
        return next;
      });
      return;
    }

    // For stop_delivered, stop_exception, etc. — trigger a KPI refresh
    // The actual data merge happens via React Query refetch
  }, []);

  useMercure<MapUpdate>(topics, handleMessage, topics.length > 0);

  return updates;
}
