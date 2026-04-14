import { useQuery } from '@tanstack/react-query';
import { api } from '../client';
import type { PaginatedResponse, RouteListItem } from '../types';

export function useActiveRoutes(enabled: boolean) {
  return useQuery({
    queryKey: ['active-routes-summary'],
    queryFn: () => api.get<PaginatedResponse<RouteListItem>>('/api/admin/routes?status=ACTIVE&limit=5'),
    enabled,
    staleTime: 30_000,
  });
}
