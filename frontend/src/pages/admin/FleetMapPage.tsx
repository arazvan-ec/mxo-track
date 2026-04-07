import { useState, useCallback, useRef, useMemo } from 'react';
import { useFleetMapData } from '@/api/hooks/useFleetMapData';
import { useFleetKpi } from '@/api/hooks/useFleetKpi';
import { useVehicleTrail } from '@/api/hooks/useVehicleTrail';
import { useMe } from '@/api/hooks/useMe';
import { usePageLayout } from '@/api/hooks/usePageLayout';
import { FleetMap, type FleetMapHandle } from '@/components/maps/FleetMap';
import { EntityActionPanel } from '@/components/panels/EntityActionPanel';
import { useMapSelection } from '@/hooks/useMapSelection';
import { BottomSheet, type BottomSheetState } from '@/components/bottom-sheet/BottomSheet';
import { WidgetRenderer } from '@/components/bottom-sheet/WidgetRenderer';

import { SHEET_HEIGHTS } from '@/components/bottom-sheet/useBottomSheet';
import type { FleetVehicle, FleetRoute } from '@/api/types';

export function FleetMapPage() {
  const { vehicles, routes, isLoading, error, sseConnected } = useFleetMapData();
  const { data: kpi } = useFleetKpi();
  const { data: me } = useMe();
  const { layout } = usePageLayout('fleet_map');
  const mapRef = useRef<FleetMapHandle>(null);

  const [sheetState, setSheetState] = useState<BottomSheetState>('collapsed');
  const [selectedVehicleId, setSelectedVehicleId] = useState<string | null>(null);
  const [selectedRouteId, setSelectedRouteId] = useState<string | null>(null);

  const { selection, selectStop, selectVehicle, clear } = useMapSelection();

  // Trail for selected vehicle
  const { coordinates: trailCoordinates } = useVehicleTrail(selectedVehicleId);

  // Derive the route associated with selected vehicle
  const selectedVehicleRoute = selectedVehicleId
    ? routes.find((r) => {
        const vehicle = vehicles.find((v) => v.public_id === selectedVehicleId);
        return vehicle && r.vehicleName === vehicle.name;
      }) ?? null
    : null;

  // The active route for stop selection highlighting
  const activeRouteId = selectedRouteId ?? selectedVehicleRoute?.publicId ?? null;

  const handleStopClick = useCallback(
    (routePublicId: string, sequence: number) => {
      const route = routes.find((r) => r.publicId === routePublicId);
      const stop = route?.stops.find((s) => s.sequence === sequence);
      if (!stop) return;

      // Auto-select the route when clicking a stop
      if (!selectedRouteId || selectedRouteId !== routePublicId) {
        setSelectedVehicleId(null);
        setSelectedRouteId(routePublicId);
      }

      selectStop(`stop-${routePublicId}-${sequence}`, {
        sequence: stop.sequence,
        address: stop.address,
        status: stop.status,
        recipientName: stop.recipient,
        shipmentPublicId: stop.shipmentPublicId,
        routePublicId,
        lat: stop.lat,
        lng: stop.lng,
      });

      if (stop.lat && stop.lng) {
        const bottomPadding = window.innerHeight * SHEET_HEIGHTS[sheetState];
        mapRef.current?.flyTo(stop.lng, stop.lat, 16, { bottom: bottomPadding });
      }
    },
    [routes, selectedRouteId, selectStop, sheetState],
  );

  const handleSelectVehicle = useCallback(
    (vehicle: FleetVehicle) => {
      setSelectedRouteId(null);

      if (selectedVehicleId === vehicle.public_id) {
        setSelectedVehicleId(null);
        clear();
        return;
      }

      setSelectedVehicleId(vehicle.public_id);
      const route = routes.find((r) => r.vehicleName === vehicle.name);

      selectVehicle(vehicle.public_id, {
        publicId: vehicle.public_id,
        name: vehicle.name,
        speed: vehicle.last_position?.speed,
        course: vehicle.last_position?.course,
        driverName: vehicle.driver_name,
        routePublicId: route?.publicId,
        routeName: route?.name,
      });

      // Fly to vehicle position
      if (vehicle.last_position) {
        const bottomPadding = window.innerHeight * SHEET_HEIGHTS[sheetState];
        mapRef.current?.flyTo(vehicle.last_position.lng, vehicle.last_position.lat, undefined, { bottom: bottomPadding });
      } else {
        // Fly to route stops if no position
        const vehicleRoute = routes.find((r) => r.vehicleName === vehicle.name);
        if (vehicleRoute) {
          const validStops = vehicleRoute.stops.filter((s) => s.lat && s.lng);
          if (validStops.length > 0) {
            mapRef.current?.fitBounds(validStops);
          }
        }
      }
    },
    [selectedVehicleId, routes, vehicles, selectVehicle, clear, sheetState],
  );

  const handleSelectRoute = useCallback(
    (route: FleetRoute) => {
      setSelectedVehicleId(null);
      clear();

      if (selectedRouteId === route.publicId) {
        setSelectedRouteId(null);
        return;
      }

      setSelectedRouteId(route.publicId);

      // Fly to route bounds
      const validStops = route.stops.filter((s) => s.lat && s.lng);
      if (validStops.length > 0) {
        mapRef.current?.fitBounds(validStops);
      }
    },
    [selectedRouteId, clear],
  );

  const handleVehicleClick = useCallback(
    (vehicleId: string) => {
      const vehicle = vehicles.find((v) => v.public_id === vehicleId);
      if (vehicle) handleSelectVehicle(vehicle);
    },
    [vehicles, handleSelectVehicle],
  );

  // Selected stop sequence for marker highlighting
  const selectedStopSequence =
    selection?.type === 'stop'
      ? (selection.data as { sequence: number }).sequence
      : null;

  // Widget system data
  const pageData = useMemo(
    () => ({
      kpi,
      routes,
      vehicles,
      selectedRouteId,
      onSelectRoute: handleSelectRoute,
      onStopClick: handleStopClick,
      selectedVehicleId,
    }),
    [kpi, routes, vehicles, selectedRouteId, handleSelectRoute, handleStopClick, selectedVehicleId],
  );

  return (
    <>
      <FleetMap
          ref={mapRef}
          vehicles={vehicles}
          routes={routes}
          trailCoordinates={trailCoordinates}
          onVehicleClick={handleVehicleClick}
          onStopClick={handleStopClick}
          selectedRouteId={activeRouteId}
          selectedStopSequence={selectedStopSequence}
        />
        <BottomSheet
          state={sheetState}
          onStateChange={setSheetState}
          title={
            <span className="flex items-center gap-2">
              Fleet Map
              <span
                className={`inline-block w-2 h-2 rounded-full ${sseConnected ? 'bg-green-400' : 'bg-red-400'}`}
                title={sseConnected ? 'Live' : 'Disconnected'}
              />
            </span>
          }
          isLoading={isLoading}
          error={error}
          loadingText="Loading fleet data..."
        >
          <div className="space-y-4">
            {/* Entity Action Panel — outside widget system (selection-driven) */}
            {selection && (
              <div className="px-4">
                <EntityActionPanel
                  selection={selection}
                  userRole={me?.role}
                  onClose={clear}
                />
              </div>
            )}

            <WidgetRenderer
              layout={layout}
              sheetState={sheetState}
              pageData={pageData}
            />
          </div>
        </BottomSheet>
    </>
  );
}
