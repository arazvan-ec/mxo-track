import { useQuery } from '@tanstack/react-query';
import { api } from '../client';
import type { PaginatedResponse, DriverListItem } from '../types';

export interface DriverListParams {
  page?: number;
  limit?: number;
}

function buildQuery(params: DriverListParams): string {
  const qs = new URLSearchParams();
  for (const [k, v] of Object.entries(params)) {
    if (v != null && v !== '') qs.set(k, String(v));
  }
  const str = qs.toString();
  return str ? `?${str}` : '';
}

export function useAdminDrivers(params: DriverListParams = {}) {
  return useQuery({
    queryKey: ['admin-drivers', params],
    queryFn: () => api.get<PaginatedResponse<DriverListItem>>(`/api/admin/drivers${buildQuery(params)}`),
  });
}
