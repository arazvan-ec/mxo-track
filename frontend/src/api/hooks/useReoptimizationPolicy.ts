import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../client';
import type { ReoptimizationPolicy } from '../types';

const QUERY_KEY = ['reoptimization-policies'];

export function useReoptimizationPolicies() {
  return useQuery({
    queryKey: QUERY_KEY,
    queryFn: () => api.get<ReoptimizationPolicy[]>('/api/admin/reoptimization-policies'),
  });
}

export function useCreatePolicy() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (payload: Omit<ReoptimizationPolicy, 'public_id'>) =>
      api.post<ReoptimizationPolicy>('/api/admin/reoptimization-policies', payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: QUERY_KEY }),
  });
}

export function useUpdatePolicy() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ public_id, ...payload }: ReoptimizationPolicy) =>
      api.put<ReoptimizationPolicy>(`/api/admin/reoptimization-policies/${public_id}`, payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: QUERY_KEY }),
  });
}

export function useDeletePolicy() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (publicId: string) =>
      api.delete<void>(`/api/admin/reoptimization-policies/${publicId}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: QUERY_KEY }),
  });
}
