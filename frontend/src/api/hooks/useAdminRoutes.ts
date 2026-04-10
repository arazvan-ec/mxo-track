import { useQuery } from '@tanstack/react-query';
import { api } from '../client';
import type { PaginatedResponse, RouteListItem, RouteFilterOptions } from '../types';

export interface RouteListParams {
  page?: number;
  limit?: number;
  status?: string;
  date_from?: string;
  date_to?: string;
  driver?: string;
  customer?: string;
}

function buildQuery(params: RouteListParams): string {
  const qs = new URLSearchParams();
  for (const [k, v] of Object.entries(params)) {
    if (v != null && v !== '') qs.set(k, String(v));
  }
  const str = qs.toString();
  return str ? `?${str}` : '';
}

export function useAdminRoutes(params: RouteListParams = {}) {
  return useQuery({
    queryKey: ['admin-routes', params],
    queryFn: () => api.get<PaginatedResponse<RouteListItem>>(`/api/admin/routes${buildQuery(params)}`),
  });
}

export function useRouteFilters() {
  return useQuery({
    queryKey: ['admin-routes-filters'],
    queryFn: () => api.get<RouteFilterOptions>('/api/admin/routes/filters'),
    staleTime: 5 * 60_000, // 5 min
  });
}
