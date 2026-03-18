import { Source, Layer, useMap } from 'react-map-gl/maplibre';
import { useCallback, useEffect, useMemo } from 'react';
import { ROUTE_COLORS } from '../shared/colors';
import type { PlannerShipment, PlannerCluster } from '@/api/types';

const UNASSIGNED_COLOR = '#6B7280';

export const SHIPMENT_INTERACTIVE_LAYERS = [
  'shipment-clusters',
  'shipment-unclustered',
] as const;

interface Props {
  shipments: PlannerShipment[];
  clusters?: PlannerCluster[];
  selectedShipmentIds?: Set<string>;
  onShipmentClick?: (publicId: string) => void;
}

/**
 * Native WebGL shipment layer with MapLibre clustering.
 * Replaces ShipmentMarkersLayer for 500+ point performance.
 */
export function ShipmentClusterLayer({
  shipments,
  clusters = [],
  selectedShipmentIds,
  onShipmentClick,
}: Props) {
  const { current: map } = useMap();

  // Build color lookup: shipmentId -> color
  const colorMap = useMemo(() => {
    const m = new Map<string, string>();
    clusters.forEach((cluster, idx) => {
      const color = cluster.color || ROUTE_COLORS[idx % ROUTE_COLORS.length];
      cluster.shipmentIds.forEach((id) => m.set(id, color));
    });
    return m;
  }, [clusters]);

  // GeoJSON FeatureCollection
  const geojson = useMemo(
    () => ({
      type: 'FeatureCollection' as const,
      features: shipments
        .filter((s) => s.lat && s.lng)
        .map((s) => ({
          type: 'Feature' as const,
          geometry: {
            type: 'Point' as const,
            coordinates: [s.lng, s.lat] as [number, number],
          },
          properties: {
            publicId: s.publicId,
            color: colorMap.get(s.publicId) ?? UNASSIGNED_COLOR,
            name: s.recipientName,
            address: s.address,
            selected:
              !selectedShipmentIds || selectedShipmentIds.has(s.publicId)
                ? 1
                : 0,
          },
        })),
    }),
    [shipments, colorMap, selectedShipmentIds],
  );

  // Click handler: zoom into cluster or select individual shipment
  const handleClick = useCallback(
    (e: maplibregl.MapLayerMouseEvent) => {
      const feature = e.features?.[0];
      if (!feature) return;

      // Cluster click → zoom in
      if (feature.properties?.cluster) {
        const clusterId = feature.properties.cluster_id as number;
        const source = map
          ?.getMap()
          .getSource('shipments-source') as maplibregl.GeoJSONSource;
        source?.getClusterExpansionZoom(clusterId).then((zoom: number) => {
          map?.flyTo({
            center: (feature.geometry as GeoJSON.Point).coordinates as [
              number,
              number,
            ],
            zoom,
          });
        });
        return;
      }

      // Individual point click
      const publicId = feature.properties?.publicId;
      if (publicId && onShipmentClick) {
        onShipmentClick(publicId);
      }
    },
    [map, onShipmentClick],
  );

  // Register click via map event
  useEffect(() => {
    const mapInstance = map?.getMap();
    if (!mapInstance) return;

    const handler = (e: maplibregl.MapMouseEvent) => {
      const features = mapInstance.queryRenderedFeatures(e.point, {
        layers: ['shipment-clusters', 'shipment-unclustered'],
      });
      if (features.length > 0) {
        handleClick({
          ...e,
          features,
        } as maplibregl.MapLayerMouseEvent);
      }
    };

    mapInstance.on('click', handler);
    return () => {
      mapInstance.off('click', handler);
    };
  }, [map, handleClick]);

  return (
    <Source
      id="shipments-source"
      type="geojson"
      data={geojson}
      cluster={true}
      clusterMaxZoom={14}
      clusterRadius={50}
    >
      {/* Cluster circles */}
      <Layer
        id="shipment-clusters"
        type="circle"
        filter={['has', 'point_count']}
        paint={{
          'circle-color': [
            'step',
            ['get', 'point_count'],
            '#3B82F6', // blue-500 small
            20,
            '#8B5CF6', // violet-500 medium
            50,
            '#EF4444', // red-500 large
          ],
          'circle-radius': ['step', ['get', 'point_count'], 15, 20, 20, 50, 25],
          'circle-stroke-width': 2,
          'circle-stroke-color': 'rgba(255,255,255,0.3)',
        }}
      />

      {/* Cluster count label */}
      <Layer
        id="shipment-cluster-count"
        type="symbol"
        filter={['has', 'point_count']}
        layout={{
          'text-field': '{point_count_abbreviated}',
          'text-size': 12,
          'text-font': ['Noto Sans Regular'],
        }}
        paint={{
          'text-color': '#ffffff',
        }}
      />

      {/* Individual unclustered points */}
      <Layer
        id="shipment-unclustered"
        type="circle"
        filter={['!', ['has', 'point_count']]}
        paint={{
          'circle-color': ['get', 'color'],
          'circle-radius': ['case', ['==', ['get', 'selected'], 1], 6, 4],
          'circle-stroke-width': 1.5,
          'circle-stroke-color': 'rgba(255,255,255,0.6)',
          'circle-opacity': ['case', ['==', ['get', 'selected'], 1], 1, 0.4],
        }}
      />
    </Source>
  );
}
