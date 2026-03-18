import { useState, useCallback, useRef } from 'react';
import { useFleetMapData } from '@/api/hooks/useFleetMapData';
import { useFleetKpi } from '@/api/hooks/useFleetKpi';
import { useVehicleTrail } from '@/api/hooks/useVehicleTrail';
import { FleetMap, type FleetMapHandle } from '@/components/maps/FleetMap';
import { FleetSidebar } from '@/components/fleet/FleetSidebar';
import { HeaderBar } from '@/components/fleet/HeaderBar';
import type { FleetVehicle, FleetRoute, FleetStop } from '@/api/types';

export function FleetMapPage() {
  const { vehicles, routes, isLoading, error } = useFleetMapData();
  const { data: kpi } = useFleetKpi();
  const mapRef = useRef<FleetMapHandle>(null);

  const [selectedVehicleId, setSelectedVehicleId] = useState<string | null>(null);
  const [selectedRouteId, setSelectedRouteId] = useState<string | null>(null);

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
        return vehicle && r.vehicle_name === vehicle.name;
      }) ?? null
    : null;

  const handleSelectVehicle = useCallback(
    (vehicle: FleetVehicle) => {
      setSelectedRouteId(null);

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
        const vehicleRoute = routes.find((r) => r.vehicle_name === vehicle.name);
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

      if (selectedRouteId === route.public_id) {
        setSelectedRouteId(null);
        return;
      }

      setSelectedRouteId(route.public_id);

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

  // SSE connection status (simple heuristic: if we have live data, we're connected)
  const sseConnected = vehicles.some((v) => v.last_position != null);

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

  return (
    <div className="relative flex h-full w-full overflow-hidden">
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

      <div className="flex-1 relative">
        <HeaderBar sseConnected={sseConnected} />
        <FleetMap
          ref={mapRef}
          vehicles={vehicles}
          routes={routes}
          activeStops={activeStops}
          trailCoordinates={trailCoordinates}
          onVehicleClick={handleVehicleClick}
        />
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
): { routeId: string; stops: FleetStop[] } | null {
  if (selectedRouteId) {
    const route = routes.find((r) => r.public_id === selectedRouteId);
    if (route) return { routeId: route.public_id, stops: route.stops };
  }

  if (selectedVehicleId) {
    const vehicle = vehicles.find((v) => v.public_id === selectedVehicleId);
    if (vehicle) {
      const vehicleRoute = routes.find((r) => r.vehicle_name === vehicle.name);
      if (vehicleRoute) {
        return { routeId: vehicleRoute.public_id, stops: vehicleRoute.stops };
      }
    }
  }

  return null;
}
