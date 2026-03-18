import { type ReactNode } from 'react';
import { VehicleMarker } from '../shared/VehicleMarker';
import { getVehicleColor } from '../shared/colors';

interface VehicleData {
  publicId: string;
  name: string;
  lat: number;
  lng: number;
  speed?: number;
  course?: number;
  color?: string;
  skills?: string[];
}

interface Props {
  vehicles: VehicleData[];
  onVehicleClick?: (publicId: string) => void;
  renderPopup?: (vehicle: VehicleData) => ReactNode;
}

/**
 * Renders N vehicle markers on the map.
 * Generic layer — works with any data source that provides VehicleData.
 */
export function VehicleLayer({ vehicles, onVehicleClick, renderPopup }: Props) {
  return (
    <>
      {vehicles.map((v) => (
        <VehicleMarker
          key={v.publicId}
          lng={v.lng}
          lat={v.lat}
          course={v.course}
          name={v.name}
          speed={v.speed}
          color={v.color ?? getVehicleColor({ skills: v.skills })}
          skills={v.skills}
          onClick={() => onVehicleClick?.(v.publicId)}
          popupContent={renderPopup?.(v)}
        />
      ))}
    </>
  );
}

export type { VehicleData };
