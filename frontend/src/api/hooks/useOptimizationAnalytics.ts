import { useQuery } from '@tanstack/react-query';
import { api } from '../client';
import type { OptimizerMetric, AddressRiskInfo, ReoptEvent } from '../types';

export function useOptimizerMetrics() {
  return useQuery({
    queryKey: ['optimizer-metrics'],
    queryFn: () => api.get<OptimizerMetric[]>('/api/admin/optimization/metrics'),
  });
}

export function useAddressRisks() {
  return useQuery({
    queryKey: ['address-risks'],
    queryFn: () => api.get<AddressRiskInfo[]>('/api/admin/optimization/address-risks'),
  });
}

export function useReoptHistory() {
  return useQuery({
    queryKey: ['reopt-history'],
    queryFn: () => api.get<ReoptEvent[]>('/api/admin/optimization/reopt-history'),
  });
}
