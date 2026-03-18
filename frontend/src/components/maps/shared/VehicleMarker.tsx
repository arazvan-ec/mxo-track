import { Marker, Popup } from 'react-map-gl/maplibre';
import { useState, type ReactNode } from 'react';
import { getVehicleColor } from './colors';

interface Props {
  lng: number;
  lat: number;
  course?: number;
  name: string;
  color?: string;
  speed?: number;
  skills?: string[];
  onClick?: () => void;
  popupContent?: ReactNode;
}

export function VehicleMarker({
  lng,
  lat,
  course,
  name,
  color,
  skills,
  speed,
  onClick,
  popupContent,
}: Props) {
  const [showPopup, setShowPopup] = useState(false);
  const bgColor = color ?? getVehicleColor({ skills });

  const handleClick = () => {
    onClick?.();
    if (popupContent) setShowPopup((prev) => !prev);
  };

  return (
    <>
      <Marker longitude={lng} latitude={lat} onClick={handleClick} anchor="center">
        <div
          title={`${name}${speed != null ? ` - ${Math.round(speed)} km/h` : ''}`}
          className="flex items-center justify-center w-9 h-9 rounded-full shadow-md cursor-pointer transition-transform duration-700"
          style={{
            backgroundColor: darken(bgColor),
            border: `2px solid ${bgColor}`,
            transform: course != null ? `rotate(${course}deg)` : undefined,
          }}
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg">
            <path d="M18 18.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3zm-12 0a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM19.92 6.09C19.72 5.46 19.16 5 18.5 5h-11c-.66 0-1.21.42-1.42 1.02L4 12v6c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h10v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-6l-2.08-5.91zM7.5 15a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm9 0a1.5 1.5 0 110-3 1.5 1.5 0 010 3zM5.81 10l1.04-3h10.29l1.04 3H5.81z" />
          </svg>
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
          className="fleet-vehicle-popup"
        >
          {popupContent}
        </Popup>
      )}
    </>
  );
}

function darken(hex: string): string {
  const r = Math.round(parseInt(hex.slice(1, 3), 16) * 0.6);
  const g = Math.round(parseInt(hex.slice(3, 5), 16) * 0.6);
  const b = Math.round(parseInt(hex.slice(5, 7), 16) * 0.6);
  return '#' + [r, g, b].map((c) => c.toString(16).padStart(2, '0')).join('');
}
