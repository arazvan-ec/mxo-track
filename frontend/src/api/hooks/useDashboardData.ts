import { useQuery } from '@tanstack/react-query';
import { api } from '../client';

interface HealthResponse {
  status: string;
  health: {
    db_ok: boolean;
    redis_ok: boolean;
    traccar_ok: boolean;
    mercure_ok: boolean;
    osrm_ok: boolean;
    vroom_ok: boolean;
  };
  live: {
    database: { ok: boolean; latency_ms: number };
    redis: { ok: boolean; latency_ms: number };
    traccar: { ok: boolean; latency_ms: number };
    mercure: { ok: boolean; latency_ms: number };
    osrm: { ok: boolean; latency_ms: number };
    vroom: { ok: boolean; latency_ms: number };
    positions: { row_count: number; warning: boolean };
    disk: { db_size_mb: number };
    last_ingestion: { timestamp: string | null; seconds_ago: number | null };
  };
  metrics: {
    active_routes: number;
    pending_stops: number;
    import_runs_today: number;
    positions_ingested_last_hour: number;
  };
  generated_at: string;
}

export function useDashboardData() {
  const query = useQuery({
    queryKey: ['dashboard-health'],
    queryFn: () => api.get<HealthResponse>('/admin/health'),
    refetchInterval: 30 * 1000,
    staleTime: 10 * 1000,
  });

  return {
    health: query.data?.health ?? null,
    live: query.data?.live ?? null,
    metrics: query.data?.metrics ?? null,
    lastRefresh: query.data?.generated_at ?? null,
    isLoading: query.isLoading,
    error: query.error,
  };
}
