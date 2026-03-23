import { useQuery } from '@tanstack/react-query';
import { api } from '../client';
import type { LayoutConfig, PageKey } from '@/types/layout';

const EMPTY_LAYOUT: LayoutConfig = {
  pageKey: 'test_routing',
  scope: 'none',
  widgets: { collapsed: [], half: [], full: [] },
};

export function usePageLayout(pageKey: PageKey) {
  const query = useQuery({
    queryKey: ['page-layout', pageKey],
    queryFn: () => api.get<LayoutConfig>(`/api/page-layouts/${pageKey}`),
    staleTime: 5 * 60 * 1000, // 5 minutes
    retry: 1,
  });

  return {
    layout: query.data ?? { ...EMPTY_LAYOUT, pageKey },
    isLoading: query.isLoading,
    error: query.error,
  };
}
