import { useQuery } from '@tanstack/react-query';
import { api } from '../client';
import type { PaginatedResponse, VehicleListItem } from '../types';

export interface VehicleListParams {
  page?: number;
  limit?: number;
}

function buildQuery(params: VehicleListParams): string {
  const qs = new URLSearchParams();
  for (const [k, v] of Object.entries(params)) {
    if (v != null && v !== '') qs.set(k, String(v));
  }
  const str = qs.toString();
  return str ? `?${str}` : '';
}

export function useAdminVehicles(params: VehicleListParams = {}) {
  return useQuery({
    queryKey: ['admin-vehicles', params],
    queryFn: () => api.get<PaginatedResponse<VehicleListItem>>(`/api/admin/vehicles${buildQuery(params)}`),
  });
}
