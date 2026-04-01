import { useState, type ReactNode } from 'react';
import { Marker, Popup } from 'react-map-gl/maplibre';
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
  /** Whether this stop is currently selected */
  isSelected?: boolean;
  /** Optional popup content shown on click */
  popupContent?: ReactNode;
}

export function StopMarker({ lng, lat, sequence, status, address, onClick, routeColor, isSelected, popupContent }: Props) {
  const [showPopup, setShowPopup] = useState(false);

  // Use route color for PENDING stops; delivered/exception/skipped keep their status color
  const color = (status === 'PENDING' || status === 'pending') && routeColor
    ? routeColor
    : STOP_STATUS_COLORS[status] ?? STOP_STATUS_COLORS.pending;

  const handleClick = () => {
    onClick?.();
    if (popupContent) setShowPopup((prev) => !prev);
  };

  return (
    <>
      <Marker longitude={lng} latitude={lat} onClick={handleClick} anchor="center" style={{ zIndex: isSelected ? 10 : 1 }}>
        <div
          title={`#${sequence} - ${address}`}
          className={`flex items-center justify-center rounded-full border-2 shadow font-bold text-white cursor-pointer transition-all duration-200 ${
            isSelected
              ? 'w-9 h-9 text-sm border-white ring-2 ring-white/60 ring-offset-2 ring-offset-slate-900'
              : 'w-6 h-6 text-xs border-white'
          }`}
          style={{ backgroundColor: color }}
        >
          {sequence}
        </div>
      </Marker>

      {showPopup && popupContent && (
        <Popup
          longitude={lng}
          latitude={lat}
          onClose={() => setShowPopup(false)}
          closeButton
          closeOnClick={false}
          anchor="bottom"
          offset={20}
          className="stop-marker-popup"
        >
          {popupContent}
        </Popup>
      )}
    </>
  );
}
