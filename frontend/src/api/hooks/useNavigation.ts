import { useQuery } from '@tanstack/react-query';
import { api } from '../client';
import type { NavigationResponse } from '../types';

export function useNavigation() {
  return useQuery({
    queryKey: ['navigation'],
    queryFn: () => api.get<NavigationResponse>('/api/navigation'),
    staleTime: 60 * 60 * 1000, // 1 hour — matches server Cache-Control
  });
}
