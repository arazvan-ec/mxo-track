import { Marker } from 'react-map-gl/maplibre';
import { STOP_STATUS_COLORS } from './colors';

interface Props {
  lng: number;
  lat: number;
  sequence: number;
  status: string;
  address: string;
  onClick?: () => void;
}

export function StopMarker({ lng, lat, sequence, status, address, onClick }: Props) {
  const color = STOP_STATUS_COLORS[status] ?? STOP_STATUS_COLORS.pending;

  return (
    <Marker longitude={lng} latitude={lat} onClick={onClick} anchor="center">
      <div
        title={`#${sequence} - ${address}`}
        className="flex items-center justify-center w-6 h-6 rounded-full border-2 border-white shadow text-xs font-bold text-white cursor-pointer"
        style={{ backgroundColor: color }}
      >
        {sequence}
      </div>
    </Marker>
  );
}
