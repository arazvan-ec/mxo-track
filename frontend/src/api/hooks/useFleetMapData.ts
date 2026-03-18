import { useQuery } from '@tanstack/react-query';
import { api } from '../client';
import { useMercurePositions } from './useMercurePositions';
import type { FleetMapData, FleetVehicle } from '../types';

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

  const livePositions = useMercurePositions(vehicleIds);

  const vehicles: FleetVehicle[] = (query.data?.vehicles ?? []).map((v) => {
    const live = livePositions.get(v.public_id);
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

  return {
    vehicles,
    routes: query.data?.routes ?? [],
    isLoading: query.isLoading,
    error: query.error,
  };
}
