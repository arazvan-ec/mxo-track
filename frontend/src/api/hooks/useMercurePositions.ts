import { useCallback, useState } from 'react';
import { useMercure } from './useMercure';
import type { VehiclePosition } from '../types';

export function useMercurePositions(vehiclePublicIds: string[]) {
  const [positions, setPositions] = useState<Map<string, VehiclePosition>>(
    new Map(),
  );

  const topics = vehiclePublicIds.map(
    (id) => `/map/vehicles/${id}/position`,
  );

  const handleMessage = useCallback((data: VehiclePosition) => {
    setPositions((prev) => {
      const next = new Map(prev);
      next.set(data.vehiclePublicId, data);
      return next;
    });
  }, []);

  useMercure<VehiclePosition>(topics, handleMessage, topics.length > 0);

  return positions;
}
