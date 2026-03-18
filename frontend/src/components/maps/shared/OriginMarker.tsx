import { Marker } from 'react-map-gl/maplibre';
import { ORIGIN_COLOR } from './colors';

interface Props {
  lng: number;
  lat: number;
  address?: string;
}

export function OriginMarker({ lng, lat, address }: Props) {
  return (
    <Marker longitude={lng} latitude={lat} anchor="center">
      <div
        title={address ?? 'Origin'}
        className="flex items-center justify-center w-7 h-7 rounded-full border-2 border-white shadow text-xs font-bold text-white"
        style={{ backgroundColor: ORIGIN_COLOR }}
      >
        O
      </div>
    </Marker>
  );
}
