import { useQuery } from '@tanstack/react-query';
import { api } from '../client';
import type { PaginatedResponse, CustomerListItem } from '../types';

export interface CustomerListParams {
  page?: number;
  limit?: number;
  active?: string;
  search?: string;
  frequency?: string;
}

export interface CustomerFilters {
  frequencies: { value: string; label: string }[];
}

function buildQuery(params: CustomerListParams): string {
  const qs = new URLSearchParams();
  for (const [k, v] of Object.entries(params)) {
    if (v != null && v !== '') qs.set(k, String(v));
  }
  const str = qs.toString();
  return str ? `?${str}` : '';
}

export function useAdminCustomers(params: CustomerListParams = {}) {
  return useQuery({
    queryKey: ['admin-customers', params],
    queryFn: () => api.get<PaginatedResponse<CustomerListItem>>(`/api/admin/customers${buildQuery(params)}`),
  });
}

export function useCustomerFilters() {
  return useQuery({
    queryKey: ['admin-customers-filters'],
    queryFn: () => api.get<CustomerFilters>('/api/admin/customers/filters'),
    staleTime: 5 * 60 * 1000,
  });
}
