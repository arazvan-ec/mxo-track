import { useQuery } from '@tanstack/react-query';
import { api } from '../client';
import { useMapSubscription } from './useMapSubscription';
import type { FleetMapData, FleetVehicle, FleetRoute } from '../types';

export function useFleetMapData() {
  const query = useQuery({
    queryKey: ['fleet-map'],
    queryFn: () => api.get<FleetMapData>('/api/fleet/map-data'),
    refetchInterval: 60_000,
  });

  const vehicleIds =
    query.data?.vehicles
      .filter((v) => v.last_position)
      .map((v) => v.public_id) ?? [];

  const routeIds = query.data?.routes.map((r) => r.publicId) ?? [];

  const { positions, connected } = useMapSubscription({
    vehicleIds,
    routeIds,
  });

  // Merge live vehicle positions
  const vehicles: FleetVehicle[] = (query.data?.vehicles ?? []).map((v) => {
    const live = positions.get(v.public_id);
    if (!live) return v;
    return {
      ...v,
      last_position: {
        lat: live.lat,
        lng: live.lng,
        speed: live.speed,
        course: live.course,
        device_time: live.deviceTime,
      },
    };
  });

  const routes: FleetRoute[] = query.data?.routes ?? [];

  return {
    vehicles,
    routes,
    isLoading: query.isLoading,
    error: query.error,
    sseConnected: connected,
  };
}
