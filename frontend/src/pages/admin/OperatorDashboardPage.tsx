import { useRef, useMemo, useState, useCallback } from 'react';
import { useFleetMapData } from '@/api/hooks/useFleetMapData';
import { useFleetKpi } from '@/api/hooks/useFleetKpi';
import { useMe } from '@/api/hooks/useMe';
import { usePageLayout } from '@/api/hooks/usePageLayout';
import { MapCanvas, type MapCanvasHandle } from '@/components/maps/MapCanvas';
import { VehicleLayer, type VehicleData } from '@/components/maps/layers/VehicleLayer';
import { StopMarkersLayer } from '@/components/maps/layers/StopMarkersLayer';
import { RoutePolylineLayer } from '@/components/maps/layers/RoutePolylineLayer';
import { StopPopup } from '@/components/maps/shared/StopPopup';
import { VehiclePopup } from '@/components/fleet/VehiclePopup';
import { EntityActionPanel } from '@/components/panels/EntityActionPanel';
import { WidgetRenderer } from '@/components/bottom-sheet/WidgetRenderer';
import { useMapSelection } from '@/hooks/useMapSelection';
import type { FleetVehicle, FleetRoute } from '@/api/types';
import { BottomSheet, type BottomSheetState } from '@/components/bottom-sheet/BottomSheet';
import { SHEET_HEIGHTS } from '@/components/bottom-sheet/useBottomSheet';
import { TopBar } from '@/components/layout/TopBar';
import { NavigationSidebar } from '@/components/layout/NavigationSidebar';

export function OperatorDashboardPage() {
  const { vehicles, routes, isLoading, error } =
    useFleetMapData();
  const { data: kpi } = useFleetKpi();
  const { data: me } = useMe();
  const mapRef = useRef<MapCanvasHandle>(null);
  const [navOpen, setNavOpen] = useState(false);
  const [sheetState, setSheetState] = useState<BottomSheetState>('collapsed');
  const [expandedRouteId, setExpandedRouteId] = useState<string | null>(null);
  const [showArrows, setShowArrows] = useState(true);
  const { selection, selectStop, selectVehicle, clear } = useMapSelection();
  const { layout } = usePageLayout('fleet_map');

  // Transform fleet vehicles to VehicleData for VehicleLayer
  const vehicleMarkers: VehicleData[] = useMemo(
    () =>
      vehicles
        .filter((v): v is FleetVehicle & { last_position: NonNullable<FleetVehicle['last_position']> } =>
          v.last_position != null,
        )
        .map((v) => ({
          publicId: v.public_id,
          name: v.name,
          lat: v.last_position.lat,
          lng: v.last_position.lng,
          speed: v.last_position.speed,
          course: v.last_position.course,
          skills: v.skills,
          color: v.marker_color,
        })),
    [vehicles],
  );

  // Compute initial center from all vehicles and route stops
  const initialCenter = useMemo(() => {
    const points: Array<{ lat: number; lng: number }> = [];
    for (const v of vehicles) {
      if (v.last_position) points.push(v.last_position);
    }
    for (const r of routes) {
      for (const s of r.stops) {
        if (s.lat && s.lng) points.push({ lat: s.lat, lng: s.lng });
      }
    }
    if (points.length === 0) return { lat: 40.416, lng: -3.703 };
    const avgLat = points.reduce((sum, p) => sum + p.lat, 0) / points.length;
    const avgLng = points.reduce((sum, p) => sum + p.lng, 0) / points.length;
    return { lat: avgLat, lng: avgLng };
  }, [vehicles, routes]);

  const activeRoutes = routes.filter(
    (r) => r.status === 'ACTIVE' || r.status === 'PLANNED',
  );

  // When a route is selected (expanded), show only that route on the map
  const visibleRoutes = expandedRouteId
    ? activeRoutes.filter((r) => r.publicId === expandedRouteId)
    : activeRoutes;

  const onSelectRoute = useCallback(
    (route: FleetRoute) => {
      const willExpand = expandedRouteId !== route.publicId;
      setExpandedRouteId((prev) =>
        prev === route.publicId ? null : route.publicId,
      );
      if (willExpand) {
        const pts = route.stops
          .filter((s) => s.lat && s.lng)
          .map((s) => ({ lat: s.lat, lng: s.lng }));
        if (pts.length > 0) {
          mapRef.current?.fitBounds(pts);
        }
      }
    },
    [expandedRouteId],
  );

  // Build stop markers for all active routes
  const allStopMarkers = useMemo(
    () =>
      activeRoutes.flatMap((route) =>
        route.stops
          .filter((s) => s.lat && s.lng)
          .map((s) => ({
            lat: s.lat,
            lng: s.lng,
            sequence: s.sequence,
            status: s.status,
            address: s.address,
            recipientName: s.recipient,
            shipmentPublicId: s.shipmentPublicId,
            routePublicId: route.publicId,
            routeColor: route.color,
          })),
      ),
    [activeRoutes],
  );

  const handleStopClick = useCallback(
    (routePublicId: string, sequence: number) => {
      const stop = allStopMarkers.find(
        (s) => s.routePublicId === routePublicId && s.sequence === sequence,
      );
      if (!stop) return;
      selectStop(`stop-${routePublicId}-${sequence}`, {
        sequence: stop.sequence,
        address: stop.address,
        status: stop.status,
        recipientName: stop.recipientName,
        shipmentPublicId: stop.shipmentPublicId,
        routePublicId: stop.routePublicId,
        lat: stop.lat,
        lng: stop.lng,
      });
      const bottomPadding = window.innerHeight * SHEET_HEIGHTS[sheetState];
      mapRef.current?.flyTo(stop.lng, stop.lat, 16, { bottom: bottomPadding });
    },
    [allStopMarkers, selectStop, sheetState],
  );

  // Widget system data
  const pageData = useMemo(
    () => ({
      kpi,
      routes: activeRoutes,
      selectedRouteId: expandedRouteId,
      onSelectRoute,
      onStopClick: handleStopClick,
    }),
    [kpi, activeRoutes, expandedRouteId, onSelectRoute, handleStopClick],
  );

  const handleVehicleClick = useCallback(
    (publicId: string) => {
      const vehicle = vehicles.find((v) => v.public_id === publicId);
      if (!vehicle) return;
      const route = routes.find((r) => r.vehicleName === vehicle.name);
      selectVehicle(publicId, {
        publicId,
        name: vehicle.name,
        speed: vehicle.last_position?.speed,
        course: vehicle.last_position?.course,
        driverName: vehicle.driver_name,
        routePublicId: route?.publicId,
        routeName: route?.name,
      });
    },
    [vehicles, routes, selectVehicle],
  );

  return (
    <div className="flex flex-col h-screen w-full">
      {navOpen && <NavigationSidebar mode="overlay" onClose={() => setNavOpen(false)} />}
      <TopBar compact onMenuClick={() => setNavOpen(true)} />
      <div className="flex-1 relative overflow-hidden">
        <MapCanvas
          ref={mapRef}
          initialCenter={initialCenter}
          initialZoom={6}
        >
          {/* Route polylines — filtered to selected route when one is expanded */}
          {visibleRoutes.map((route) =>
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

          {/* Stop markers — filtered to selected route when one is expanded */}
          {visibleRoutes.map((route) => (
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
              keyPrefix={`op-${route.publicId}-`}
              onStopClick={(seq) => handleStopClick(route.publicId, seq)}
              routeColor={route.color}
              selectedSequence={
                selection?.type === 'stop' && selection.entityId.includes(route.publicId)
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

          {/* Vehicle markers */}
          <VehicleLayer
            vehicles={vehicleMarkers}
            onVehicleClick={handleVehicleClick}
            renderPopup={(v) => {
              const vehicle = vehicles.find((fv) => fv.public_id === v.publicId);
              if (!vehicle) return null;
              const route = routes.find((r) => r.vehicleName === vehicle.name);
              return <VehiclePopup vehicle={vehicle} routeName={route?.name} />;
            }}
          />
        </MapCanvas>
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
          title="Operations Dashboard"
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
      </div>
    </div>
  );
}

