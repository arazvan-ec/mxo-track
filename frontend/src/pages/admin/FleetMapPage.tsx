import { useState, useCallback, useRef } from 'react';
import { useFleetMapData } from '@/api/hooks/useFleetMapData';
import { useFleetKpi } from '@/api/hooks/useFleetKpi';
import { useVehicleTrail } from '@/api/hooks/useVehicleTrail';
import { useMe } from '@/api/hooks/useMe';
import { FleetMap, type FleetMapHandle } from '@/components/maps/FleetMap';
import { FleetSidebar } from '@/components/fleet/FleetSidebar';
import { StopPopup } from '@/components/maps/shared/StopPopup';
import { EntityActionPanel } from '@/components/panels/EntityActionPanel';
import { useMapSelection } from '@/hooks/useMapSelection';
import { BottomSheet, type BottomSheetState } from '@/components/bottom-sheet/BottomSheet';
import { TopBar } from '@/components/layout/TopBar';
import { NavigationSidebar } from '@/components/layout/NavigationSidebar';
import { SHEET_HEIGHTS } from '@/components/bottom-sheet/useBottomSheet';
import type { FleetVehicle, FleetRoute, FleetStop } from '@/api/types';

export function FleetMapPage() {
  const { vehicles, routes, isLoading, error, sseConnected } = useFleetMapData();
  const { data: kpi } = useFleetKpi();
  const { data: me } = useMe();
  const mapRef = useRef<FleetMapHandle>(null);

  const [navOpen, setNavOpen] = useState(false);
  const [sheetState, setSheetState] = useState<BottomSheetState>('collapsed');
  const [selectedVehicleId, setSelectedVehicleId] = useState<string | null>(null);
  const [selectedRouteId, setSelectedRouteId] = useState<string | null>(null);
  const [showArrows, setShowArrows] = useState(true);

  const { selection, selectStop, selectVehicle, clear } = useMapSelection();

  // Trail for selected vehicle
  const { coordinates: trailCoordinates } = useVehicleTrail(selectedVehicleId);

  // Derive active stops from selection
  const activeStops = getActiveStops(
    vehicles,
    routes,
    selectedVehicleId,
    selectedRouteId,
  );

  // Derive the route associated with selected vehicle
  const selectedVehicleRoute = selectedVehicleId
    ? routes.find((r) => {
        const vehicle = vehicles.find((v) => v.public_id === selectedVehicleId);
        return vehicle && r.vehicleName === vehicle.name;
      }) ?? null
    : null;

  const handleStopClick = useCallback(
    (sequence: number) => {
      const stop = activeStops?.stops.find((s) => s.sequence === sequence);
      if (!stop) return;

      selectStop(`stop-${activeStops!.routeId}-${sequence}`, {
        sequence: stop.sequence,
        address: stop.address,
        status: stop.status,
        recipientName: stop.recipient,
        shipmentPublicId: stop.shipmentPublicId,
        routePublicId: activeStops!.routeId,
        lat: stop.lat,
        lng: stop.lng,
      });

      if (stop.lat && stop.lng) {
        const bottomPadding = window.innerHeight * SHEET_HEIGHTS[sheetState];
        mapRef.current?.flyTo(stop.lng, stop.lat, 16, { bottom: bottomPadding });
      }
    },
    [activeStops, selectStop, sheetState],
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

  return (
    <div className="flex flex-col h-screen w-full">
      {navOpen && <NavigationSidebar mode="overlay" onClose={() => setNavOpen(false)} />}
      <TopBar compact onMenuClick={() => setNavOpen(true)} />
      <div className="flex-1 relative overflow-hidden">
        <FleetMap
          ref={mapRef}
          vehicles={vehicles}
          routes={routes}
          activeStops={activeStops}
          trailCoordinates={trailCoordinates}
          onVehicleClick={handleVehicleClick}
          onStopClick={handleStopClick}
          selectedStopSequence={selectedStopSequence}
          showArrows={showArrows}
          renderStopPopup={(stop) => (
            <StopPopup
              sequence={stop.sequence}
              address={stop.address}
              status={stop.status}
              recipientName={stop.recipient}
              shipmentPublicId={stop.shipmentPublicId}
            />
          )}
        />
        <button
          type="button"
          className={`absolute top-4 left-4 z-10 px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors ${
            showArrows
              ? 'bg-slate-800/90 text-slate-200 border-slate-600 hover:bg-slate-700'
              : 'bg-slate-800/50 text-slate-400 border-slate-700 hover:bg-slate-700/50'
          }`}
          onClick={() => setShowArrows((v) => !v)}
          title={showArrows ? 'Ocultar flechas de direccion' : 'Mostrar flechas de direccion'}
        >
          {showArrows ? 'ON' : 'OFF'}
        </button>
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
          <div className="px-4 pb-4 space-y-4">
            {/* Entity Action Panel */}
            {selection && (
              <EntityActionPanel
                selection={selection}
                userRole={me?.role}
                onClose={clear}
              />
            )}

            <FleetSidebar
              vehicles={vehicles}
              routes={routes}
              kpi={kpi}
              selectedVehicleId={selectedVehicleId}
              selectedRouteId={selectedRouteId}
              onSelectVehicle={handleSelectVehicle}
              onSelectRoute={handleSelectRoute}
              selectedVehicleRoute={selectedVehicleRoute}
            />
          </div>
        </BottomSheet>
      </div>
    </div>
  );
}

/** Determine which stops to show based on current selection */
function getActiveStops(
  vehicles: FleetVehicle[],
  routes: FleetRoute[],
  selectedVehicleId: string | null,
  selectedRouteId: string | null,
): { routeId: string; stops: FleetStop[]; polyline?: string; color?: string } | null {
  if (selectedRouteId) {
    const route = routes.find((r) => r.publicId === selectedRouteId);
    if (route) return { routeId: route.publicId, stops: route.stops, polyline: route.polyline, color: route.color };
  }

  if (selectedVehicleId) {
    const vehicle = vehicles.find((v) => v.public_id === selectedVehicleId);
    if (vehicle) {
      const vehicleRoute = routes.find((r) => r.vehicleName === vehicle.name);
      if (vehicleRoute) {
        return { routeId: vehicleRoute.publicId, stops: vehicleRoute.stops, polyline: vehicleRoute.polyline, color: vehicleRoute.color };
      }
    }
  }

  return null;
}
