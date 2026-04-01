import { type ReactNode } from 'react';
import { StopMarker } from '../shared/StopMarker';

interface StopData {
  lat: number;
  lng: number;
  sequence: number;
  status: string;
  address: string;
  recipientName?: string;
  shipmentPublicId?: string;
}

interface Props {
  stops: StopData[];
  keyPrefix?: string;
  onStopClick?: (sequence: number) => void;
  /** Color PENDING stop markers to match their route */
  routeColor?: string;
  /** Sequence number of the currently selected stop */
  selectedSequence?: number | null;
  /** Render popup content for a stop */
  renderPopup?: (stop: StopData) => ReactNode;
}

/**
 * Renders N stop markers on the map.
 * Generic layer — works with any data source that provides StopData.
 */
export function StopMarkersLayer({ stops, keyPrefix = '', onStopClick, routeColor, selectedSequence, renderPopup }: Props) {
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
            routeColor={routeColor}
            isSelected={stop.sequence === selectedSequence}
            popupContent={renderPopup?.(stop)}
          />
        ) : null,
      )}
    </>
  );
}

export type { StopData };
