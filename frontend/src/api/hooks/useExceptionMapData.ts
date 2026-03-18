import { useQuery } from '@tanstack/react-query';
import { api } from '../client';

export interface ExceptionPoint {
  lat: number;
  lng: number;
  address: string;
  type: string;
  routeName: string;
  date: string | null;
  notes: string | null;
}

interface ExceptionMapResponse {
  exceptions: ExceptionPoint[];
}

export function useExceptionMapData(from: string, to: string) {
  const params = new URLSearchParams();
  if (from) params.set('from', from);
  if (to) params.set('to', to);
  const qs = params.toString();

  const query = useQuery({
    queryKey: ['exception-map', from, to],
    queryFn: () =>
      api.get<ExceptionMapResponse>(
        `/api/map/exceptions${qs ? `?${qs}` : ''}`,
      ),
  });

  return {
    exceptions: query.data?.exceptions ?? [],
    isLoading: query.isLoading,
    error: query.error,
  };
}
