import { useState, useCallback, useRef } from 'react';
import { useFleetMapData } from '@/api/hooks/useFleetMapData';
import { useFleetKpi } from '@/api/hooks/useFleetKpi';
import { useVehicleTrail } from '@/api/hooks/useVehicleTrail';
import { FleetMap, type FleetMapHandle } from '@/components/maps/FleetMap';
import { FleetSidebar } from '@/components/fleet/FleetSidebar';
import { HeaderBar } from '@/components/fleet/HeaderBar';
import { DualMenuShell } from '@/components/layout/DualMenuShell';
import type { FleetVehicle, FleetRoute, FleetStop } from '@/api/types';

export function FleetMapPage() {
  const { vehicles, routes, isLoading, error, sseConnected } = useFleetMapData();
  const { data: kpi } = useFleetKpi();
  const mapRef = useRef<FleetMapHandle>(null);

  const [selectedVehicleId, setSelectedVehicleId] = useState<string | null>(null);
  const [selectedRouteId, setSelectedRouteId] = useState<string | null>(null);
  const [selectedStopSequence, setSelectedStopSequence] = useState<number | null>(null);

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
      setSelectedStopSequence(sequence === selectedStopSequence ? null : sequence);

      const stop = activeStops?.stops.find((s) => s.sequence === sequence);
      if (stop?.lat && stop?.lng) {
        mapRef.current?.flyTo(stop.lng, stop.lat, 16);
      }
    },
    [activeStops, selectedStopSequence],
  );

  const handleSelectVehicle = useCallback(
    (vehicle: FleetVehicle) => {
      setSelectedRouteId(null);
      setSelectedStopSequence(null);

      if (selectedVehicleId === vehicle.public_id) {
        setSelectedVehicleId(null);
        return;
      }

      setSelectedVehicleId(vehicle.public_id);

      // Fly to vehicle position
      if (vehicle.last_position) {
        mapRef.current?.flyTo(vehicle.last_position.lng, vehicle.last_position.lat);
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
    [selectedVehicleId, routes, vehicles],
  );

  const handleSelectRoute = useCallback(
    (route: FleetRoute) => {
      setSelectedVehicleId(null);
      setSelectedStopSequence(null);

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
    [selectedRouteId],
  );

  const handleVehicleClick = useCallback(
    (vehicleId: string) => {
      const vehicle = vehicles.find((v) => v.public_id === vehicleId);
      if (vehicle) handleSelectVehicle(vehicle);
    },
    [vehicles, handleSelectVehicle],
  );

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-full bg-slate-900">
        <div className="text-slate-500">Loading fleet data...</div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex items-center justify-center h-full bg-slate-900">
        <div className="text-red-500">Error: {error.message}</div>
      </div>
    );
  }

  const sidebar = (
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
  );

  return (
    <DualMenuShell dataSidebar={sidebar} dataSidebarWidth="w-80">
      <HeaderBar sseConnected={sseConnected} />
      <FleetMap
        ref={mapRef}
        vehicles={vehicles}
        routes={routes}
        activeStops={activeStops}
        trailCoordinates={trailCoordinates}
        onVehicleClick={handleVehicleClick}
        onStopClick={handleStopClick}
        selectedStopSequence={selectedStopSequence}
      />
    </DualMenuShell>
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
