import { Marker } from 'react-map-gl/maplibre';
import { VEHICLE_COLOR } from './colors';

interface Props {
  lng: number;
  lat: number;
  course?: number;
  name: string;
  color?: string;
  onClick?: () => void;
}

export function VehicleMarker({ lng, lat, course, name, color, onClick }: Props) {
  return (
    <Marker longitude={lng} latitude={lat} onClick={onClick} anchor="center">
      <div
        title={name}
        className="flex items-center justify-center w-8 h-8 rounded-full border-2 border-white shadow-md cursor-pointer"
        style={{
          backgroundColor: color ?? VEHICLE_COLOR,
          transform: course != null ? `rotate(${course}deg)` : undefined,
        }}
      >
        <svg
          width="16"
          height="16"
          viewBox="0 0 24 24"
          fill="white"
          xmlns="http://www.w3.org/2000/svg"
        >
          <path d="M12 2L4.5 20.29L5.21 21L12 18L18.79 21L19.5 20.29L12 2Z" />
        </svg>
      </div>
    </Marker>
  );
}
