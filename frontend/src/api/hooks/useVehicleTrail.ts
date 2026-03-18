import { useQuery } from '@tanstack/react-query';
import { api } from '../client';

interface PositionItem {
  lat: number;
  lng: number;
}

interface PositionsResponse {
  items: PositionItem[];
}

/**
 * Fetches historical positions for a vehicle (trail polyline).
 * Only fetches when vehiclePublicId is provided.
 */
export function useVehicleTrail(vehiclePublicId: string | null) {
  const query = useQuery({
    queryKey: ['vehicle-trail', vehiclePublicId],
    queryFn: () =>
      api.get<PositionsResponse>(
        `/api/vehicles/${encodeURIComponent(vehiclePublicId!)}/positions?order=ASC&limit=500`,
      ),
    enabled: vehiclePublicId != null,
    staleTime: 30_000,
  });

  const coordinates: [number, number][] =
    query.data?.items?.map((p) => [p.lng, p.lat] as [number, number]) ?? [];

  return {
    coordinates,
    isLoading: query.isLoading && vehiclePublicId != null,
  };
}
