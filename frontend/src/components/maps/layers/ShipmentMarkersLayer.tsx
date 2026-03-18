import { Marker } from 'react-map-gl/maplibre';
import { ROUTE_COLORS } from '../shared/colors';
import type { PlannerShipment, PlannerCluster } from '@/api/types';

const UNASSIGNED_COLOR = '#6B7280'; // gray-500

interface Props {
  shipments: PlannerShipment[];
  clusters?: PlannerCluster[];
  selectedShipmentIds?: Set<string>;
  onShipmentClick?: (publicId: string) => void;
}

/**
 * Renders shipment points as small colored dots on the map.
 * Color by cluster assignment (uses ROUTE_COLORS palette).
 * Un-clustered shipments shown in gray.
 */
export function ShipmentMarkersLayer({
  shipments,
  clusters = [],
  selectedShipmentIds,
  onShipmentClick,
}: Props) {
  // Build a lookup: shipmentId -> cluster color
  const colorMap = new Map<string, string>();
  clusters.forEach((cluster, idx) => {
    const color = cluster.color || ROUTE_COLORS[idx % ROUTE_COLORS.length];
    cluster.shipmentIds.forEach((id) => colorMap.set(id, color));
  });

  return (
    <>
      {shipments.map((shipment) => {
        if (!shipment.lat || !shipment.lng) return null;

        const isSelected = !selectedShipmentIds || selectedShipmentIds.has(shipment.publicId);
        const color = colorMap.get(shipment.publicId) ?? UNASSIGNED_COLOR;

        return (
          <Marker
            key={shipment.publicId}
            longitude={shipment.lng}
            latitude={shipment.lat}
            anchor="center"
            onClick={() => onShipmentClick?.(shipment.publicId)}
          >
            <div
              title={`${shipment.recipientName} - ${shipment.address}`}
              className="rounded-full border-2 border-white shadow cursor-pointer transition-opacity"
              style={{
                backgroundColor: color,
                width: isSelected ? 12 : 8,
                height: isSelected ? 12 : 8,
                opacity: isSelected ? 1 : 0.4,
              }}
            />
          </Marker>
        );
      })}
    </>
  );
}
