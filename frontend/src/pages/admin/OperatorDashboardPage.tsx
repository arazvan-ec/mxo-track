import { useRef, useMemo, useState } from 'react';
import { useFleetMapData } from '@/api/hooks/useFleetMapData';
import { useFleetKpi } from '@/api/hooks/useFleetKpi';
import { MapCanvas, type MapCanvasHandle } from '@/components/maps/MapCanvas';
import { VehicleLayer, type VehicleData } from '@/components/maps/layers/VehicleLayer';
import type { FleetVehicle, FleetRoute } from '@/api/types';
import { BottomSheet, type BottomSheetState } from '@/components/bottom-sheet/BottomSheet';
import { TopBar } from '@/components/layout/TopBar';
import { NavigationSidebar } from '@/components/layout/NavigationSidebar';

export function OperatorDashboardPage() {
  const { vehicles, routes, isLoading, error, sseConnected } =
    useFleetMapData();
  const { data: kpi } = useFleetKpi();
  const mapRef = useRef<MapCanvasHandle>(null);
  const [navOpen, setNavOpen] = useState(false);
  const [sheetState, setSheetState] = useState<BottomSheetState>('collapsed');

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

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-screen bg-slate-900">
        <div className="text-slate-500">Loading fleet data...</div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex items-center justify-center h-screen bg-slate-900">
        <div className="text-red-500">Error: {error.message}</div>
      </div>
    );
  }

  const activeRoutes = routes.filter(
    (r) => r.status === 'ACTIVE' || r.status === 'PLANNED',
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
          <VehicleLayer vehicles={vehicleMarkers} />
        </MapCanvas>
        <BottomSheet state={sheetState} onStateChange={setSheetState} title="Operations Dashboard">
          <div className="px-4 pb-4 space-y-4">
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
                    onFocus={() => {
                      const pts = route.stops
                        .filter((s) => s.lat && s.lng)
                        .map((s) => ({ lat: s.lat, lng: s.lng }));
                      if (pts.length > 0) {
                        mapRef.current?.fitBounds(pts);
                      }
                    }}
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
  onFocus,
}: {
  route: FleetRoute;
  onFocus: () => void;
}) {
  const delivered = route.deliveredStops;
  const total = route.totalStops;
  const pct = total > 0 ? Math.round((delivered / total) * 100) : 0;

  const statusColor =
    route.status === 'ACTIVE'
      ? 'bg-emerald-500/20 text-emerald-400'
      : 'bg-blue-500/20 text-blue-400';

  return (
    <button
      type="button"
      className="w-full bg-slate-700/50 hover:bg-slate-700 rounded-lg p-3 text-left transition-colors"
      onClick={onFocus}
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
        <span
          className={`text-[10px] font-medium px-1.5 py-0.5 rounded-full ${statusColor}`}
        >
          {route.status === 'ACTIVE' ? 'Active' : 'Planned'}
        </span>
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
  );
}
