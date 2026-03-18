import { useRef } from 'react';
import { useTestRoutingData } from '@/api/hooks/useTestRoutingData';
import type {
  TestRoutingRoute,
  TestRoutingStop,
} from '@/api/hooks/useTestRoutingData';
import { MapCanvas, type MapCanvasHandle } from '@/components/maps/MapCanvas';
import { RoutePolylineLayer } from '@/components/maps/layers/RoutePolylineLayer';
import { StopMarkersLayer } from '@/components/maps/layers/StopMarkersLayer';
import { ROUTE_COLORS } from '@/components/maps/shared/colors';

export function TestRoutingPage() {
  const { data, isLoading, error } = useTestRoutingData();
  const mapRef = useRef<MapCanvasHandle>(null);

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-screen bg-slate-900">
        <div className="text-center">
          <div className="animate-spin h-8 w-8 border-2 border-blue-500 border-t-transparent rounded-full mx-auto mb-4" />
          <p className="text-slate-400 text-sm">
            Running route optimization...
          </p>
          <p className="text-slate-500 text-xs mt-1">
            This may take a few seconds
          </p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex items-center justify-center h-screen bg-slate-900">
        <div className="text-red-400 text-center">
          <p className="text-lg font-medium mb-2">Optimization Error</p>
          <p className="text-sm text-red-500">{error.message}</p>
        </div>
      </div>
    );
  }

  if (!data) return null;

  const { origin, allStopsBefore, routesData, metrics } = data;

  // Compute map bounds from all stops
  const allPoints = [
    { lat: origin.lat, lng: origin.lng },
    ...allStopsBefore.map((s) => ({ lat: s.lat, lng: s.lng })),
  ];

  return (
    <div className="flex h-screen w-full bg-slate-900">
      {/* Sidebar */}
      <aside className="w-96 flex-shrink-0 overflow-y-auto border-r border-slate-700 bg-slate-900 p-4 space-y-4">
        {/* Header */}
        <div>
          <h1 className="text-lg font-bold text-slate-100">
            Test Routing: VROOM + OSRM
          </h1>
          <p className="text-xs text-slate-400 mt-1">
            Comparison of original vs optimized routes
          </p>
        </div>

        {/* Global Metrics */}
        <div className="grid grid-cols-2 gap-2">
          <MetricCard
            label="Distance (original)"
            value={`${metrics.distanceBeforeKm} km`}
            color="text-red-400"
          />
          <MetricCard
            label="Distance (optimized)"
            value={`${metrics.distanceAfterKm} km`}
            color="text-blue-400"
          />
          <MetricCard
            label="Savings"
            value={`${metrics.savedPercent}%`}
            color="text-emerald-400"
          />
          <MetricCard
            label="Total duration"
            value={`${metrics.totalDurationMinutes} min`}
            color="text-slate-200"
          />
          <MetricCard
            label="Routes"
            value={String(metrics.routeCount)}
            color="text-slate-200"
          />
          <MetricCard
            label="Total stops"
            value={String(metrics.stopCount)}
            color="text-slate-200"
          />
        </div>

        {/* Original stop order */}
        <div className="bg-slate-800 rounded-lg overflow-hidden">
          <div className="px-3 py-2 border-b border-slate-700">
            <h3 className="text-sm font-semibold text-red-400">
              Original order (1 route, unoptimized)
            </h3>
          </div>
          <div className="max-h-48 overflow-y-auto">
            <StopTable stops={allStopsBefore} color="text-red-400" />
          </div>
        </div>

        {/* Per-route cards */}
        {routesData.map((route, idx) => (
          <RouteCard
            key={route.name}
            route={route}
            color={ROUTE_COLORS[idx % ROUTE_COLORS.length]}
            onFocus={() => {
              const points = route.stopsAfter
                .filter((s) => s.lat && s.lng)
                .map((s) => ({ lat: s.lat, lng: s.lng }));
              if (points.length > 0) {
                mapRef.current?.fitBounds(points);
              }
            }}
          />
        ))}
      </aside>

      {/* Map */}
      <div className="flex-1 relative">
        <MapCanvas
          ref={mapRef}
          initialCenter={{ lat: origin.lat, lng: origin.lng }}
          initialZoom={12}
        >
          {/* Original route (dashed red) */}
          {data.polylineBefore && (
            <RoutePolylineLayer
              id="original"
              polyline={data.polylineBefore}
              color="#EF4444"
              dashed
            />
          )}

          {/* Optimized routes */}
          {routesData.map((route, idx) => {
            if (!route.polylineAfter) return null;
            const color = ROUTE_COLORS[idx % ROUTE_COLORS.length];
            return (
              <RoutePolylineLayer
                key={route.name}
                id={`opt-${idx}`}
                polyline={route.polylineAfter}
                color={color}
              />
            );
          })}

          {/* Stop markers for optimized routes */}
          {routesData.map((route, idx) => (
            <StopMarkersLayer
              key={`stops-${idx}`}
              keyPrefix={`route-${idx}-`}
              stops={route.stopsAfter.map((s) => ({
                lat: s.lat,
                lng: s.lng,
                sequence: s.seq,
                status: 'PENDING',
                address: s.address,
              }))}
            />
          ))}
        </MapCanvas>

        {/* Fit bounds button */}
        <button
          type="button"
          className="absolute top-4 left-4 bg-slate-800/90 text-slate-200 px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-slate-700 transition-colors border border-slate-600"
          onClick={() => mapRef.current?.fitBounds(allPoints)}
        >
          Fit all
        </button>

        {/* Legend */}
        <div className="absolute bottom-4 left-4 bg-slate-800/90 border border-slate-700 rounded-lg px-3 py-2 space-y-1.5">
          <div className="flex items-center gap-2">
            <div className="w-6 h-0.5 border-t-2 border-dashed border-red-500" />
            <span className="text-xs text-slate-300">Original</span>
          </div>
          {routesData.map((route, idx) => (
            <div key={route.name} className="flex items-center gap-2">
              <div
                className="w-6 h-0.5 rounded"
                style={{
                  backgroundColor:
                    ROUTE_COLORS[idx % ROUTE_COLORS.length],
                }}
              />
              <span className="text-xs text-slate-300">{route.name}</span>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

function MetricCard({
  label,
  value,
  color,
}: {
  label: string;
  value: string;
  color: string;
}) {
  return (
    <div className="bg-slate-800 rounded-lg p-3">
      <p className="text-xs text-slate-400">{label}</p>
      <p className={`text-lg font-bold ${color}`}>{value}</p>
    </div>
  );
}

function StopTable({
  stops,
  color,
}: {
  stops: TestRoutingStop[];
  color: string;
}) {
  return (
    <table className="w-full text-xs">
      <thead className="text-[10px] text-slate-500 uppercase bg-slate-800/50 sticky top-0">
        <tr>
          <th className="py-1.5 px-3 text-left">#</th>
          <th className="py-1.5 px-3 text-left">Recipient</th>
          <th className="py-1.5 px-3 text-left">Address</th>
        </tr>
      </thead>
      <tbody className="divide-y divide-slate-700/50">
        {stops.map((stop) => (
          <tr key={stop.seq}>
            <td className={`py-1 px-3 font-medium ${color}`}>{stop.seq}</td>
            <td className="py-1 px-3 text-slate-200">{stop.recipient}</td>
            <td className="py-1 px-3 text-slate-400 truncate max-w-[140px]">
              {stop.address}
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

function RouteCard({
  route,
  color,
  onFocus,
}: {
  route: TestRoutingRoute;
  color: string;
  onFocus: () => void;
}) {
  return (
    <div className="bg-slate-800 rounded-lg overflow-hidden">
      {/* Header */}
      <div className="px-3 py-2 border-b border-slate-700 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <div
            className="w-3 h-3 rounded-full"
            style={{ backgroundColor: color }}
          />
          <h3 className="text-sm font-semibold text-slate-100">
            {route.name}
          </h3>
          <span className="text-xs text-slate-400">{route.vehicle}</span>
        </div>
        <div className="flex items-center gap-2">
          <span className="text-xs text-slate-400">
            {route.stopCount} stops
          </span>
          <button
            type="button"
            className="text-xs text-blue-400 hover:text-blue-300"
            onClick={onFocus}
          >
            Focus
          </button>
        </div>
      </div>

      {/* Route metrics */}
      <div className="grid grid-cols-4 gap-1 px-2 py-2">
        <div className="text-center p-1.5 rounded bg-slate-700/50">
          <p className="text-[10px] text-slate-500">Before</p>
          <p className="text-xs font-bold text-slate-200">
            {route.distanceBeforeKm} km
          </p>
        </div>
        <div className="text-center p-1.5 rounded bg-slate-700/50">
          <p className="text-[10px] text-slate-500">After</p>
          <p className="text-xs font-bold text-slate-200">
            {route.distanceAfterKm} km
          </p>
        </div>
        <div className="text-center p-1.5 rounded bg-slate-700/50">
          <p className="text-[10px] text-slate-500">Saved</p>
          <p className="text-xs font-bold text-emerald-400">
            {route.savedPercent}%
          </p>
        </div>
        <div className="text-center p-1.5 rounded bg-slate-700/50">
          <p className="text-[10px] text-slate-500">Time</p>
          <p className="text-xs font-bold text-slate-200">
            {route.timing?.totalTimeMinutes ?? route.durationMinutes} min
          </p>
        </div>
      </div>

      {/* Side-by-side stop comparison */}
      <div className="grid grid-cols-2 gap-1 px-2 pb-2">
        <div className="bg-slate-700/30 rounded overflow-hidden">
          <div className="px-2 py-1 border-b border-slate-700">
            <h4 className="text-[10px] font-semibold text-slate-400 uppercase">
              Assigned order
            </h4>
          </div>
          <div className="max-h-32 overflow-y-auto">
            <MiniStopList stops={route.stopsBefore} color="text-slate-400" />
          </div>
        </div>
        <div className="bg-slate-700/30 rounded overflow-hidden">
          <div className="px-2 py-1 border-b border-slate-700">
            <h4 className="text-[10px] font-semibold text-slate-400 uppercase">
              Optimized order
            </h4>
          </div>
          <div className="max-h-32 overflow-y-auto">
            <MiniStopList stops={route.stopsAfter} color={`text-[${color}]`} colorHex={color} />
          </div>
        </div>
      </div>
    </div>
  );
}

function MiniStopList({
  stops,
  color,
  colorHex,
}: {
  stops: TestRoutingStop[];
  color: string;
  colorHex?: string;
}) {
  return (
    <div className="divide-y divide-slate-700/30">
      {stops.map((stop) => (
        <div key={stop.seq} className="px-2 py-0.5 flex gap-1 items-baseline">
          <span
            className={`text-[10px] font-bold flex-shrink-0 ${colorHex ? '' : color}`}
            style={colorHex ? { color: colorHex } : undefined}
          >
            {stop.seq}
          </span>
          <span className="text-[10px] text-slate-300 truncate">
            {stop.recipient}
          </span>
        </div>
      ))}
    </div>
  );
}
