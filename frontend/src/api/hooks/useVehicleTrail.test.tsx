import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { type ReactNode } from 'react';
import { useVehicleTrail } from './useVehicleTrail';

vi.mock('@/api/client', () => ({
  api: { get: vi.fn() },
}));

import { api } from '@/api/client';

const mockGet = vi.mocked(api.get);

function createWrapper() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return function Wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
  };
}

describe('useVehicleTrail', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('returns empty coordinates when vehiclePublicId is null', () => {
    const { result } = renderHook(() => useVehicleTrail(null), {
      wrapper: createWrapper(),
    });

    expect(result.current.coordinates).toEqual([]);
    expect(mockGet).not.toHaveBeenCalled();
  });

  it('isLoading is false when vehiclePublicId is null', () => {
    const { result } = renderHook(() => useVehicleTrail(null), {
      wrapper: createWrapper(),
    });

    expect(result.current.isLoading).toBe(false);
  });

  it('returns coordinates in [lng, lat] format after successful fetch', async () => {
    mockGet.mockResolvedValueOnce({
      items: [
        { lat: 19.4326, lng: -99.1332 },
        { lat: 19.4400, lng: -99.1400 },
      ],
    });

    const { result } = renderHook(() => useVehicleTrail('vehicle-abc'), {
      wrapper: createWrapper(),
    });

    await waitFor(() => {
      expect(result.current.coordinates.length).toBe(2);
    });

    expect(result.current.coordinates).toEqual([
      [-99.1332, 19.4326],
      [-99.1400, 19.4400],
    ]);
    expect(result.current.isLoading).toBe(false);
  });

  it('calls correct API endpoint with encoded vehiclePublicId', async () => {
    mockGet.mockResolvedValueOnce({ items: [] });

    const { result } = renderHook(() => useVehicleTrail('vehicle/special id'), {
      wrapper: createWrapper(),
    });

    await waitFor(() => {
      expect(mockGet).toHaveBeenCalled();
    });

    expect(mockGet).toHaveBeenCalledWith(
      '/api/vehicles/vehicle%2Fspecial%20id/positions?order=ASC&limit=500',
    );

    expect(result.current.coordinates).toEqual([]);
  });
});
