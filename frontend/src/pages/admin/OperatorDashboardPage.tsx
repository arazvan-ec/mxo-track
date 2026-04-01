import { useRef, useMemo, useState, useCallback } from 'react';
import { useFleetMapData } from '@/api/hooks/useFleetMapData';
import { useFleetKpi } from '@/api/hooks/useFleetKpi';
import { useMe } from '@/api/hooks/useMe';
import { MapCanvas, type MapCanvasHandle } from '@/components/maps/MapCanvas';
import { VehicleLayer, type VehicleData } from '@/components/maps/layers/VehicleLayer';
import { StopMarkersLayer } from '@/components/maps/layers/StopMarkersLayer';
import { RoutePolylineLayer } from '@/components/maps/layers/RoutePolylineLayer';
import { StopPopup } from '@/components/maps/shared/StopPopup';
import { VehiclePopup } from '@/components/fleet/VehiclePopup';
import { EntityActionPanel } from '@/components/panels/EntityActionPanel';
import { useMapSelection } from '@/hooks/useMapSelection';
import type { FleetVehicle, FleetRoute, FleetStop } from '@/api/types';
import { BottomSheet, type BottomSheetState } from '@/components/bottom-sheet/BottomSheet';
import { SHEET_HEIGHTS } from '@/components/bottom-sheet/useBottomSheet';
import { TopBar } from '@/components/layout/TopBar';
import { NavigationSidebar } from '@/components/layout/NavigationSidebar';

export function OperatorDashboardPage() {
  const { vehicles, routes, isLoading, error, sseConnected } =
    useFleetMapData();
  const { data: kpi } = useFleetKpi();
  const { data: me } = useMe();
  const mapRef = useRef<MapCanvasHandle>(null);
  const [navOpen, setNavOpen] = useState(false);
  const [sheetState, setSheetState] = useState<BottomSheetState>('collapsed');
  const [expandedRouteId, setExpandedRouteId] = useState<string | null>(null);
  const { selection, selectStop, selectVehicle, clear } = useMapSelection();

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
            recipientName: s.recipientName,
            shipmentPublicId: s.shipmentPublicId,
            routePublicId: route.publicId,
            routeColor: route.color,
          })),
      ),
    [activeRoutes],
  );

  const handleStopClick = useCallback(
    (sequence: number) => {
      const stop = allStopMarkers.find((s) => s.sequence === sequence);
      if (!stop) return;
      selectStop(`stop-${stop.routePublicId}-${sequence}`, {
        sequence: stop.sequence,
        address: stop.address,
        status: stop.status,
        recipientName: stop.recipientNameName,
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
          {/* Route polylines */}
          {activeRoutes.map((route) =>
            route.polyline ? (
              <RoutePolylineLayer
                key={route.publicId}
                id={route.publicId}
                polyline={route.polyline}
                color={route.color}
              />
            ) : null,
          )}

          {/* Stop markers for all routes */}
          {activeRoutes.map((route) => (
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
                  recipientName: s.recipientName,
                  shipmentPublicId: s.shipmentPublicId,
                }))}
              keyPrefix={`op-${route.publicId}-`}
              onStopClick={handleStopClick}
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
                  recipientName={stop.recipientNameName}
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
        <BottomSheet
          state={sheetState}
          onStateChange={setSheetState}
          title="Operations Dashboard"
          isLoading={isLoading}
          error={error}
          loadingText="Loading fleet data..."
        >
          <div className="px-4 pb-4 space-y-4">
            {/* Entity Action Panel — shown when a map element is selected */}
            {selection && (
              <EntityActionPanel
                selection={selection}
                userRole={me?.role}
                onClose={clear}
              />
            )}

            {/* SSE connected indicator + KPI section */}
            <div className="space-y-2">
              <div className="flex items-center gap-2">
                <span className="relative flex h-2 w-2">
                  <span
                    className={`absolute inline-flex h-full w-full animate-ping rounded-full opacity-75 ${
                      sseConnected ? 'bg-emerald-400' : 'bg-red-400'
                    }`}
                  />
                  <span
                    className={`relative inline-flex h-2 w-2 rounded-full ${
                      sseConnected ? 'bg-emerald-500' : 'bg-red-500'
                    }`}
                  />
                </span>
                <span className="text-xs font-medium text-slate-400">
                  {sseConnected ? 'Live' : 'Disconnected'}
                </span>
              </div>
              <div className="flex items-center gap-4">
                <KpiItem label="Active Routes" value={kpi?.active_routes ?? 0} color="text-indigo-400" />
                <KpiItem label="Vehicles" value={kpi?.total_vehicles ?? 0} color="text-violet-400" />
                <KpiItem label="Pending" value={kpi?.pending_stops ?? 0} color="text-amber-400" />
              </div>
            </div>

            {/* Active routes */}
            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <h2 className="text-sm font-semibold text-slate-100">Active Routes</h2>
                <span className="text-xs text-slate-400">{activeRoutes.length} routes</span>
              </div>
              {activeRoutes.length === 0 ? (
                <div className="text-center py-8">
                  <p className="text-xs text-slate-500">No active routes</p>
                </div>
              ) : (
                activeRoutes.map((route) => (
                  <RouteListItem
                    key={route.publicId}
                    route={route}
                    expanded={expandedRouteId === route.publicId}
                    onToggle={() =>
                      setExpandedRouteId((prev) =>
                        prev === route.publicId ? null : route.publicId,
                      )
                    }
                    onFocus={() => {
                      const pts = route.stops
                        .filter((s) => s.lat && s.lng)
                        .map((s) => ({ lat: s.lat, lng: s.lng }));
                      if (pts.length > 0) {
                        mapRef.current?.fitBounds(pts);
                      }
                    }}
                    onStopClick={handleStopClick}
                  />
                ))
              )}
            </div>
          </div>
        </BottomSheet>
      </div>
    </div>
  );
}

function KpiItem({
  label,
  value,
  color,
}: {
  label: string;
  value: number;
  color: string;
}) {
  return (
    <div className="flex items-center gap-2">
      <span className="text-xs text-slate-400">{label}</span>
      <span className={`text-sm font-bold ${color}`}>{value}</span>
    </div>
  );
}

function RouteListItem({
  route,
  expanded,
  onToggle,
  onFocus,
  onStopClick,
}: {
  route: FleetRoute;
  expanded: boolean;
  onToggle: () => void;
  onFocus: () => void;
  onStopClick?: (sequence: number) => void;
}) {
  const delivered = route.deliveredStops;
  const total = route.totalStops;
  const pct = total > 0 ? Math.round((delivered / total) * 100) : 0;

  const statusColor =
    route.status === 'ACTIVE'
      ? 'bg-emerald-500/20 text-emerald-400'
      : 'bg-blue-500/20 text-blue-400';

  const sortedStops = useMemo(
    () => [...route.stops].sort((a, b) => a.sequence - b.sequence),
    [route.stops],
  );

  return (
    <div className="bg-slate-700/50 rounded-lg overflow-hidden">
      <button
        type="button"
        className="w-full hover:bg-slate-700 p-3 text-left transition-colors"
        onClick={() => {
          onToggle();
          onFocus();
        }}
      >
        <div className="flex items-center justify-between mb-2">
          <div className="flex items-center gap-2 min-w-0">
            <div
              className="w-2.5 h-2.5 rounded-full flex-shrink-0"
              style={{ backgroundColor: route.color }}
            />
            <span className="text-xs font-medium text-slate-100 truncate">
              {route.name}
            </span>
          </div>
          <div className="flex items-center gap-2">
            <span
              className={`text-[10px] font-medium px-1.5 py-0.5 rounded-full ${statusColor}`}
            >
              {route.status === 'ACTIVE' ? 'Active' : 'Planned'}
            </span>
            <svg
              className={`w-3.5 h-3.5 text-slate-400 transition-transform ${expanded ? 'rotate-180' : ''}`}
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              strokeWidth={2}
            >
              <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
          </div>
        </div>

        {/* Driver / Vehicle */}
        <div className="flex items-center gap-3 mb-2">
          {route.driverName && (
            <span className="text-[10px] text-slate-400 truncate">
              {route.driverName}
            </span>
          )}
          {route.vehicleName && (
            <span className="text-[10px] text-slate-500 truncate">
              {route.vehicleName}
            </span>
          )}
        </div>

        {/* Progress bar */}
        <div className="flex items-center gap-2">
          <div className="flex-1 h-1.5 bg-slate-600 rounded-full overflow-hidden">
            <div
              className={`h-full rounded-full transition-all duration-500 ${
                pct === 100
                  ? 'bg-emerald-500'
                  : pct > 50
                    ? 'bg-blue-500'
                    : 'bg-amber-500'
              }`}
              style={{ width: `${pct}%` }}
            />
          </div>
          <span className="text-[10px] text-slate-400 flex-shrink-0">
            {delivered}/{total}
          </span>
        </div>
      </button>

      {/* Expandable stops list */}
      {expanded && sortedStops.length > 0 && (
        <div className="border-t border-slate-600/50 px-3 pb-3 pt-2 space-y-1.5">
          {sortedStops.map((stop) => (
            <StopItem
              key={`${route.publicId}-${stop.sequence}`}
              stop={stop}
              onClick={() => onStopClick?.(stop.sequence)}
            />
          ))}
        </div>
      )}
    </div>
  );
}

function StopItem({ stop, onClick }: { stop: FleetStop; onClick?: () => void }) {
  const statusConfig: Record<string, { icon: string; color: string }> = {
    DELIVERED: { icon: '\u2713', color: 'text-emerald-400' },
    SKIPPED: { icon: '\u2717', color: 'text-red-400' },
    EXCEPTION: { icon: '!', color: 'text-orange-400' },
    PENDING: { icon: '\u25CB', color: 'text-slate-500' },
  };
  const cfg = statusConfig[stop.status] ?? statusConfig.PENDING;

  return (
    <button
      type="button"
      className="flex items-start gap-2 py-1 w-full text-left hover:bg-slate-600/30 rounded px-1 -mx-1 transition-colors"
      onClick={onClick}
    >
      <span className={`text-xs font-mono w-4 text-center flex-shrink-0 ${cfg.color}`}>
        {cfg.icon}
      </span>
      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-1.5">
          <span className="text-[10px] text-slate-500 font-mono">{stop.sequence}</span>
          <span className="text-[11px] text-slate-200 truncate">{stop.address}</span>
        </div>
        {stop.recipientName && (
          <span className="text-[10px] text-slate-500 truncate block">{stop.recipientName}</span>
        )}
      </div>
      <span className={`text-[9px] font-medium flex-shrink-0 ${cfg.color}`}>
        {stop.status}
      </span>
    </button>
  );
}
