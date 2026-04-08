import { useState } from 'react';
import { RoutePolylineLayer } from './RoutePolylineLayer';
import { StopMarkersLayer } from './StopMarkersLayer';
import { StopPopup } from '../shared/StopPopup';
import type { FleetRoute } from '@/api/types';

interface Selection {
  type: string;
  entityId: string;
  data: unknown;
}

interface Props {
  routes: FleetRoute[];
  onStopClick?: (routePublicId: string, sequence: number) => void;
  /** Used by FleetMap: highlights stops for this route */
  selectedRouteId?: string | null;
  /** Used by FleetMap: highlights this stop sequence within selectedRouteId */
  selectedStopSequence?: number | null;
  /** Used by OperatorDashboard: highlights stops by entityId match */
  selection?: Selection | null;
  /** Prefix for marker keys to avoid collisions between pages */
  keyPrefix?: string;
}

/**
 * Shared route layers: polylines + stop markers + direction arrows toggle.
 * Both FleetMapPage and OperatorDashboardPage use this to ensure consistent behavior.
 */
export function RouteMapLayers({
  routes,
  onStopClick,
  selectedRouteId,
  selectedStopSequence,
  selection,
  keyPrefix = 'route-',
}: Props) {
  const [showArrows, setShowArrows] = useState(true);

  return (
    <>
      {routes.map((route) =>
        route.polyline ? (
          <RoutePolylineLayer
            key={route.publicId}
            id={route.publicId}
            polyline={route.polyline}
            color={route.color}
            showArrows={showArrows}
          />
        ) : null,
      )}

      {routes.map((route) => (
        <StopMarkersLayer
          key={`stops-${route.publicId}`}
          stops={route.stops
            .filter((s) => s.lat && s.lng)
            .map((s) => ({
              lat: s.lat,
              lng: s.lng,
              sequence: s.sequence,
              status: s.status,
              address: s.address,
              recipientName: s.recipient,
              shipmentPublicId: s.shipmentPublicId,
            }))}
          keyPrefix={`${keyPrefix}${route.publicId}-`}
          onStopClick={(seq) => onStopClick?.(route.publicId, seq)}
          routeColor={route.color}
          selectedSequence={
            selectedRouteId === route.publicId
              ? selectedStopSequence
              : selection?.type === 'stop' && selection.entityId.includes(route.publicId)
                ? (selection.data as { sequence: number }).sequence
                : null
          }
          renderPopup={(stop) => (
            <StopPopup
              sequence={stop.sequence}
              address={stop.address}
              status={stop.status}
              recipientName={stop.recipientName}
              shipmentPublicId={stop.shipmentPublicId}
            />
          )}
        />
      ))}

      <button
        type="button"
        className="absolute top-4 left-4 z-10 px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors theme-card-overlay"
        style={{
          color: showArrows ? 'var(--color-text-primary)' : 'var(--color-text-muted)',
          borderColor: 'var(--color-border-subtle)',
        }}
        onClick={() => setShowArrows((v) => !v)}
        title={showArrows ? 'Ocultar flechas de direccion' : 'Mostrar flechas de direccion'}
      >
        {showArrows ? 'ON' : 'OFF'}
      </button>
    </>
  );
}
