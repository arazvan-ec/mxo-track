import { useQuery } from '@tanstack/react-query';
import { api } from '../client';
import type { PaginatedResponse, ShipmentListItem } from '../types';

export interface ShipmentListParams {
  page?: number;
  limit?: number;
  customer?: string;
  priority?: string;
  date_from?: string;
  date_to?: string;
}

function buildQuery(params: ShipmentListParams): string {
  const qs = new URLSearchParams();
  for (const [k, v] of Object.entries(params)) {
    if (v != null && v !== '') qs.set(k, String(v));
  }
  const str = qs.toString();
  return str ? `?${str}` : '';
}

export function useAdminShipments(params: ShipmentListParams = {}) {
  return useQuery({
    queryKey: ['admin-shipments', params],
    queryFn: () => api.get<PaginatedResponse<ShipmentListItem>>(`/api/admin/shipments${buildQuery(params)}`),
  });
}

export function useShipmentFilters() {
  return useQuery({
    queryKey: ['admin-shipments-filters'],
    queryFn: () => api.get<{ customers: Array<{ publicId: string; name: string }> }>('/api/admin/shipments/filters'),
    staleTime: 5 * 60_000,
  });
}
