import { useQuery } from '@tanstack/react-query';
import { api } from '../client';

export interface TestRoutingStop {
  seq: number;
  recipient: string;
  address: string;
  lat: number;
  lng: number;
}

export interface TestRoutingTiming {
  totalDistanceKm: number;
  drivingTimeMinutes: number;
  deliveryTimeMinutes: number;
  totalTimeMinutes: number;
}

export interface TestRoutingRoute {
  name: string;
  vehicle: string;
  stopsBefore: TestRoutingStop[];
  stopsAfter: TestRoutingStop[];
  polylineAfter: string | null;
  distanceBeforeKm: number;
  distanceAfterKm: number;
  savedPercent: number;
  durationMinutes: number;
  timing: TestRoutingTiming | null;
  stopCount: number;
}

export interface TestRoutingMetrics {
  distanceBeforeKm: number;
  distanceAfterKm: number;
  savedPercent: number;
  totalDurationMinutes: number;
  stopCount: number;
  routeCount: number;
}

export interface TestRoutingData {
  origin: { lat: number; lng: number; address: string };
  allStopsBefore: TestRoutingStop[];
  polylineBefore: string | null;
  osrmAvailable: boolean;
  routesData: TestRoutingRoute[];
  metrics: TestRoutingMetrics;
}

export function useTestRoutingData() {
  return useQuery({
    queryKey: ['test-routing'],
    queryFn: () => api.get<TestRoutingData>('/api/map/test-routing'),
    staleTime: Infinity, // Static data — no refetch
    retry: 1,
  });
}
