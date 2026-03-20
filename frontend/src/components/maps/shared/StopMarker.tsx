import { Marker } from 'react-map-gl/maplibre';
import { STOP_STATUS_COLORS } from './colors';

interface Props {
  lng: number;
  lat: number;
  sequence: number;
  status: string;
  address: string;
  onClick?: () => void;
  /** Override color for PENDING stops to match route color */
  routeColor?: string;
}

export function StopMarker({ lng, lat, sequence, status, address, onClick, routeColor }: Props) {
  // Use route color for PENDING stops; delivered/exception/skipped keep their status color
  const color = (status === 'PENDING' || status === 'pending') && routeColor
    ? routeColor
    : STOP_STATUS_COLORS[status] ?? STOP_STATUS_COLORS.pending;

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
