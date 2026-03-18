import { StopMarker } from '../shared/StopMarker';

interface StopData {
  lat: number;
  lng: number;
  sequence: number;
  status: string;
  address: string;
}

interface Props {
  stops: StopData[];
  keyPrefix?: string;
  onStopClick?: (sequence: number) => void;
}

/**
 * Renders N stop markers on the map.
 * Generic layer — works with any data source that provides StopData.
 */
export function StopMarkersLayer({ stops, keyPrefix = '', onStopClick }: Props) {
  return (
    <>
      {stops.map((stop) =>
        stop.lat && stop.lng ? (
          <StopMarker
            key={`${keyPrefix}${stop.sequence}`}
            lng={stop.lng}
            lat={stop.lat}
            sequence={stop.sequence}
            status={stop.status}
            address={stop.address}
            onClick={() => onStopClick?.(stop.sequence)}
          />
        ) : null,
      )}
    </>
  );
}

export type { StopData };
