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

  // Widget system data
  const pageData = useMemo(
    () => ({
      kpi,
      routes: activeRoutes,
      selectedRouteId: expandedRouteId,
      onSelectRoute,
    }),
    [kpi, activeRoutes, expandedRouteId, onSelectRoute],
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
    (sequence: number) => {
      const stop = allStopMarkers.find((s) => s.sequence === sequence);
      if (!stop) return;
      selectStop(`stop-${stop.routePublicId}-${sequence}`, {
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
  onStopClick,
}: {
  route: FleetRoute;
  expanded: boolean;
  onToggle: () => void;
  onStopClick?: (sequence: number) => void;
}) {
  const delivered = route.deliveredStops;
  const total = route.totalStops;
  const pct = total > 0 ? Math.round((delivered / total) * 100) : 0;
  const [expandedStopKey, setExpandedStopKey] = useState<string | null>(null);

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
        onClick={onToggle}
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
          {sortedStops.map((stop) => {
            const key = `${route.publicId}-${stop.sequence}`;
            return (
              <StopItem
                key={key}
                stop={stop}
                expanded={expandedStopKey === key}
                onToggle={() => setExpandedStopKey((prev) => (prev === key ? null : key))}
                onLocate={() => onStopClick?.(stop.sequence)}
              />
            );
          })}
        </div>
      )}
    </div>
  );
}

function StopItem({
  stop,
  expanded,
  onToggle,
  onLocate,
}: {
  stop: FleetStop;
  expanded: boolean;
  onToggle: () => void;
  onLocate: () => void;
}) {
  const statusConfig: Record<string, { icon: string; color: string }> = {
    DELIVERED: { icon: '\u2713', color: 'text-emerald-400' },
    SKIPPED: { icon: '\u2717', color: 'text-red-400' },
    EXCEPTION: { icon: '!', color: 'text-orange-400' },
    PENDING: { icon: '\u25CB', color: 'text-slate-500' },
  };
  const cfg = statusConfig[stop.status] ?? statusConfig.PENDING;
  const recipientName = stop.recipientName ?? stop.recipient;

  return (
    <div className={`rounded transition-colors ${expanded ? 'bg-slate-600/40' : ''}`}>
      <button
        type="button"
        className="flex items-start gap-2 py-1.5 w-full text-left hover:bg-slate-600/30 rounded px-1.5 transition-colors"
        onClick={onToggle}
      >
        <span className={`text-xs font-mono w-4 text-center flex-shrink-0 ${cfg.color}`}>
          {cfg.icon}
        </span>
        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-1.5">
            <span className="text-[10px] text-slate-500 font-mono">{stop.sequence}</span>
            <span className="text-[11px] text-slate-200 truncate">{stop.address}</span>
          </div>
          {recipientName && (
            <span className="text-[10px] text-slate-500 truncate block">{recipientName}</span>
          )}
        </div>
        <span className={`text-[9px] font-medium flex-shrink-0 ${cfg.color}`}>
          {stop.status}
        </span>
      </button>

      {expanded && (
        <div className="px-1.5 pb-2 pt-0.5 ml-6 space-y-1.5">
          {/* Detail fields */}
          <div className="space-y-0.5">
            {stop.recipientPhone && (
              <div className="text-[10px] text-slate-400 flex items-center gap-1">
                <svg className="w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                </svg>
                <span className="font-mono">{stop.recipientPhone}</span>
              </div>
            )}
            {stop.status === 'DELIVERED' && stop.deliveredAt && (
              <div className="text-[10px] text-emerald-400 flex items-center gap-1">
                <svg className="w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span>Entregado: {new Date(stop.deliveredAt).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' })}</span>
              </div>
            )}
            {stop.status === 'EXCEPTION' && stop.exceptionCode && (
              <div className="text-[10px] text-orange-400 flex items-center gap-1">
                <svg className="w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span>{stop.exceptionCode}</span>
              </div>
            )}
            {stop.status === 'EXCEPTION' && stop.exceptionNotes && (
              <div className="text-[10px] text-slate-500 ml-4 italic">{stop.exceptionNotes}</div>
            )}
          </div>

          {/* Action buttons */}
          <div className="flex flex-wrap gap-1">
            <button
              type="button"
              onClick={(e) => { e.stopPropagation(); onLocate(); }}
              className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium bg-blue-500/20 text-blue-400 hover:bg-blue-500/30 transition-colors"
            >
              <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                <path strokeLinecap="round" strokeLinejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              Localizar
            </button>
            <button
              type="button"
              onClick={(e) => { e.stopPropagation(); navigator.clipboard.writeText(stop.address); }}
              className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium bg-slate-500/20 text-slate-300 hover:bg-slate-500/30 transition-colors"
            >
              <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
              </svg>
              Copiar
            </button>
            {stop.recipientPhone && (
              <a
                href={`tel:${stop.recipientPhone}`}
                onClick={(e) => e.stopPropagation()}
                className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium bg-emerald-500/20 text-emerald-400 hover:bg-emerald-500/30 transition-colors"
              >
                <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                </svg>
                Llamar
              </a>
            )}
            {stop.shipmentPublicId && (
              <a
                href={`/admin/shipments/${stop.shipmentPublicId}`}
                onClick={(e) => e.stopPropagation()}
                className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium bg-violet-500/20 text-violet-400 hover:bg-violet-500/30 transition-colors"
              >
                <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
                Ver envio
              </a>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
