import { useState, useCallback } from 'react';

export type MapEntityType = 'stop' | 'vehicle';

export interface StopSelectionData {
  sequence: number;
  address: string;
  status: string;
  recipientName?: string;
  recipientPhone?: string;
  shipmentPublicId?: string;
  routePublicId?: string;
  etaTime?: string;
  deliveredAt?: string;
  exceptionCode?: string;
  lat?: number;
  lng?: number;
}

export interface VehicleSelectionData {
  publicId: string;
  name: string;
  speed?: number;
  course?: number;
  driverName?: string;
  routePublicId?: string;
  routeName?: string;
}

export interface MapSelection {
  type: MapEntityType;
  entityId: string;
  data: StopSelectionData | VehicleSelectionData;
}

export interface UseMapSelectionReturn {
  selection: MapSelection | null;
  selectStop: (entityId: string, data: StopSelectionData) => void;
  selectVehicle: (entityId: string, data: VehicleSelectionData) => void;
  clear: () => void;
  isSelected: (type: MapEntityType, entityId: string) => boolean;
}

export function useMapSelection(): UseMapSelectionReturn {
  const [selection, setSelection] = useState<MapSelection | null>(null);

  const selectStop = useCallback((entityId: string, data: StopSelectionData) => {
    setSelection((prev) => {
      // Toggle off if same stop clicked again
      if (prev?.type === 'stop' && prev.entityId === entityId) return null;
      return { type: 'stop', entityId, data };
    });
  }, []);

  const selectVehicle = useCallback((entityId: string, data: VehicleSelectionData) => {
    setSelection((prev) => {
      if (prev?.type === 'vehicle' && prev.entityId === entityId) return null;
      return { type: 'vehicle', entityId, data };
    });
  }, []);

  const clear = useCallback(() => setSelection(null), []);

  const isSelected = useCallback(
    (type: MapEntityType, entityId: string) =>
      selection?.type === type && selection?.entityId === entityId,
    [selection],
  );

  return { selection, selectStop, selectVehicle, clear, isSelected };
}
