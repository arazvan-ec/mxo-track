import { useQuery } from '@tanstack/react-query';
import { api } from '../client';

export interface FleetKpi {
  total_vehicles: number;
  active_routes: number;
  pending_stops: number;
}

export function useFleetKpi() {
  return useQuery({
    queryKey: ['fleet-kpi'],
    queryFn: () => api.get<FleetKpi>('/api/fleet/summary'),
    refetchInterval: 30_000,
  });
}
