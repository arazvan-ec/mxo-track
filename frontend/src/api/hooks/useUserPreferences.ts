import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../client';

export interface UserPreferences {
  widget_default_mode: 'expanded' | 'collapsed';
}

export function useUserPreferences() {
  return useQuery({
    queryKey: ['user-preferences'],
    queryFn: () => api.get<UserPreferences>('/api/me/preferences'),
    staleTime: 5 * 60 * 1000,
  });
}

export function useUpdateUserPreferences() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: Partial<UserPreferences>) =>
      api.put<UserPreferences>('/api/me/preferences', data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['user-preferences'] });
    },
  });
}
