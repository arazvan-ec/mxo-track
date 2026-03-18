import { useQuery } from '@tanstack/react-query';
import { api } from '../client';
import type { MapData } from '../types';

/**
 * Fetches route data for analysis from /api/map/route/{publicId}.
 * Reuses the same endpoint as the route map view — admin role
 * gets comparison data (comparisonPolyline, originalStops, metrics).
 */
export function useRouteAnalysis(publicId: string | undefined) {
  const query = useQuery({
    queryKey: ['route-analysis', publicId],
    queryFn: () => api.get<MapData>(`/api/map/route/${publicId}`),
    enabled: !!publicId,
  });

  const route = query.data?.routes[0] ?? null;

  return {
    mapData: query.data ?? null,
    route,
    stops: route?.stops ?? [],
    isLoading: query.isLoading,
    error: query.error,
  };
}
