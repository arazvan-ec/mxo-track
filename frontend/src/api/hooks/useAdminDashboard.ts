import { useQuery } from '@tanstack/react-query';
import { api } from '../client';
import type { AdminDashboardResponse } from '../types';

export function useAdminDashboard() {
  return useQuery({
    queryKey: ['admin-dashboard'],
    queryFn: () => api.get<AdminDashboardResponse>('/api/admin/dashboard'),
    refetchInterval: 30_000,
  });
}
