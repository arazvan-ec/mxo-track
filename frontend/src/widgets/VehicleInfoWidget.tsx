import { VehicleInfoPanel } from '@/components/panels/VehicleInfoPanel';
import type { WidgetProps } from './types';

interface VehicleInfoData {
  vehicleInfo?: {
    name: string;
    driverName?: string;
    speed?: number;
    deviceTime?: string;
    skills?: string[];
  } | null;
}

export function VehicleInfoWidget({ data }: WidgetProps) {
  const { vehicleInfo } = data as VehicleInfoData;
  if (!vehicleInfo) return null;

  return (
    <div className="px-4 pb-3">
      <VehicleInfoPanel vehicle={vehicleInfo} />
    </div>
  );
}
