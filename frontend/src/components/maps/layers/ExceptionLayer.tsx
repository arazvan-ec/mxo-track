import { Marker } from 'react-map-gl/maplibre';
import { useState } from 'react';

export interface ExceptionData {
  lat: number;
  lng: number;
  address: string;
  type: string;
  routeName: string;
  date: string | null;
}

interface Props {
  exceptions: ExceptionData[];
}

/**
 * Renders red circle markers for delivery exceptions on the map.
 */
export function ExceptionLayer({ exceptions }: Props) {
  const [selectedIdx, setSelectedIdx] = useState<number | null>(null);

  return (
    <>
      {exceptions.map((ex, idx) => (
        <Marker
          key={`exc-${idx}`}
          longitude={ex.lng}
          latitude={ex.lat}
          anchor="center"
          onClick={(e) => {
            e.originalEvent.stopPropagation();
            setSelectedIdx(selectedIdx === idx ? null : idx);
          }}
        >
          <div className="relative group">
            {/* Red circle marker */}
            <div
              className="flex items-center justify-center w-7 h-7 rounded-full border-2 border-white/60 text-[10px] font-bold text-white cursor-pointer"
              style={{
                backgroundColor: 'rgba(239, 68, 68, 0.85)',
                boxShadow: '0 2px 6px rgba(239, 68, 68, 0.4)',
              }}
              title={`${ex.type} - ${ex.address}`}
            >
              !
            </div>

            {/* Hover tooltip */}
            <div className="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block z-10">
              <div
                className="whitespace-nowrap rounded-md px-2.5 py-1.5 text-xs text-slate-200 border border-red-500/50"
                style={{
                  backgroundColor: 'rgba(15, 23, 42, 0.92)',
                  boxShadow: '0 4px 12px rgba(0,0,0,0.3)',
                }}
              >
                <span className="font-semibold text-red-400">{ex.type}</span>
                <br />
                {ex.address}
              </div>
            </div>

            {/* Click popup */}
            {selectedIdx === idx && (
              <div className="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 z-20">
                <div
                  className="rounded-lg px-3 py-2 text-xs text-slate-200 border border-red-500/50 min-w-[200px]"
                  style={{
                    backgroundColor: 'rgba(15, 23, 42, 0.95)',
                    boxShadow: '0 4px 16px rgba(0,0,0,0.4)',
                  }}
                >
                  <div className="font-semibold text-red-400 mb-1">
                    {ex.type}
                  </div>
                  <div className="text-slate-300 mb-0.5">{ex.address}</div>
                  <div className="text-slate-500">
                    Ruta: {ex.routeName}
                  </div>
                  {ex.date && (
                    <div className="text-slate-500">Fecha: {ex.date}</div>
                  )}
                </div>
              </div>
            )}
          </div>
        </Marker>
      ))}
    </>
  );
}
