import type { MapSelection, StopSelectionData, VehicleSelectionData } from '@/hooks/useMapSelection';
import { StopActionPanel } from './StopActionPanel';
import { VehicleActionPanel } from './VehicleActionPanel';

interface Props {
  selection: MapSelection;
  userRole?: string;
  onClose: () => void;
}

export function EntityActionPanel({ selection, userRole, onClose }: Props) {
  if (selection.type === 'stop') {
    return <StopActionPanel stop={selection.data as StopSelectionData} userRole={userRole} onClose={onClose} />;
  }

  if (selection.type === 'vehicle') {
    return <VehicleActionPanel vehicle={selection.data as VehicleSelectionData} userRole={userRole} onClose={onClose} />;
  }

  return null;
}
