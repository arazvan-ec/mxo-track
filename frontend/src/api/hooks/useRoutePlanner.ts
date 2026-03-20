import { useQuery, useMutation } from '@tanstack/react-query';
import { api } from '../client';
import type {
  PlannerShipment,
  PlannerVehicle,
  PlannerLocation,
  PlannerPreviewResponse,
  DriverSuggestion,
  PlannerConfirmResponse,
} from '../types';

export function usePlannerShipments(customerId?: string) {
  return useQuery({
    queryKey: ['planner-shipments', customerId],
    queryFn: () => {
      const params = customerId ? `?customer_id=${encodeURIComponent(customerId)}` : '';
      return api.get<PlannerShipment[]>(`/admin/route-planner/shipments${params}`);
    },
    enabled: false, // manual trigger with refetch
  });
}

export function usePlannerVehicles() {
  return useQuery({
    queryKey: ['planner-vehicles'],
    queryFn: () => api.get<PlannerVehicle[]>('/admin/route-planner/vehicles'),
    enabled: false,
  });
}

export function usePlannerLocations(customerId?: string) {
  return useQuery({
    queryKey: ['planner-locations', customerId],
    queryFn: () => {
      const params = customerId ? `?customer_id=${encodeURIComponent(customerId)}` : '';
      return api.get<PlannerLocation[]>(`/admin/route-planner/locations${params}`);
    },
    enabled: false,
  });
}

export function usePlannerImportShipments(importId?: string) {
  return useQuery({
    queryKey: ['planner-import-shipments', importId],
    queryFn: () =>
      api.get<PlannerShipment[]>(
        `/admin/route-planner/import-shipments/${encodeURIComponent(importId!)}`,
      ),
    enabled: false, // manual trigger with refetch
  });
}

export function useClusterMutation() {
  return useMutation({
    mutationFn: (payload: { shipment_ids: string[]; num_clusters: number }) =>
      api.post<{ clusters: Array<{ shipmentIds: string[]; centroid: { lat: number; lng: number }; color: string }> }>(
        '/admin/route-planner/cluster',
        payload,
      ),
  });
}

export function usePreviewMutation() {
  return useMutation({
    mutationFn: (payload: {
      shipment_ids: string[];
      vehicle_ids: string[];
      origin_public_id: string | null;
      max_stops_per_route: number;
    }) => api.post<PlannerPreviewResponse>('/admin/route-planner/preview', payload),
  });
}

export function useSuggestDrivers(routeId: string | null) {
  return useQuery({
    queryKey: ['planner-suggest-drivers', routeId],
    queryFn: () =>
      api.get<DriverSuggestion[]>(
        `/admin/route-planner/suggest-drivers?route_id=${encodeURIComponent(routeId!)}`,
      ),
    enabled: !!routeId,
  });
}

export function useConfirmMutation() {
  return useMutation({
    mutationFn: (payload: { driver_assignments: Record<string, string> }) =>
      api.post<PlannerConfirmResponse>('/admin/route-planner/confirm', payload),
  });
}
