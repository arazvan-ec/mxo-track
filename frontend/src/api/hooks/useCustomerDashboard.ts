import { useQuery } from '@tanstack/react-query';
import { api } from '../client';

export interface CustomerKpis {
  total_shipments: number;
  active_routes: number;
  pending_deliveries: number;
  completed_today: number;
  exceptions: number;
}

export interface CustomerOptimizationKpis {
  monthly_km_saved: number;
  total_km_saved: number;
  monthly_time_saved_minutes: number;
  total_time_saved_minutes: number;
  avg_delivery_success_rate: number | null;
  avg_savings_percent: number | null;
  routes_with_metrics: number;
}

export function useCustomerKpis() {
  return useQuery({
    queryKey: ['customer-kpis'],
    queryFn: () => api.get<CustomerKpis>('/customer/dashboard/kpis'),
    refetchInterval: 30_000,
  });
}

export function useCustomerOptimizationKpis() {
  return useQuery({
    queryKey: ['customer-optimization-kpis'],
    queryFn: () => api.get<CustomerOptimizationKpis>('/customer/dashboard/optimization-kpis'),
    refetchInterval: 60_000,
  });
}
