import { useRef, useEffect, useMemo } from 'react';
import { useParams, Link } from 'react-router';
import { MapCanvas, type MapCanvasHandle } from '@/components/maps/MapCanvas';
import { RoutePolylineLayer } from '@/components/maps/layers/RoutePolylineLayer';
import { StopMarkersLayer } from '@/components/maps/layers/StopMarkersLayer';
import { RouteMetricsPanel } from '@/components/panels/RouteMetricsPanel';
import { useRouteAnalysis } from '@/api/hooks/useRouteAnalysis';
import type { RouteData } from '@/api/types';

/** Extract comparison stats from route data. */
function getComparisonStats(route: RouteData | null) {
  if (!route) return null;
  const metrics = route.metrics as Record<string, number> | undefined;
  if (!metrics) return null;

  return {
    plannedDistanceKm: metrics.distanceBeforeKm ?? metrics.distanceAfterKm ?? 0,
    actualDistanceKm: metrics.distanceAfterKm ?? 0,
    deviationKm: Math.abs(
      (metrics.distanceBeforeKm ?? 0) - (metrics.distanceAfterKm ?? 0),
    ),
    extraTimeMinutes: (metrics.totalTimeMinutes ?? 0) - (metrics.drivingTimeMinutes ?? 0),
  };
}

export function RouteAnalysisPage() {
  const { publicId } = useParams<{ publicId: string }>();
  const mapRef = useRef<MapCanvasHandle>(null);
  const { route, stops, isLoading, error } = useRouteAnalysis(publicId);

  const comparison = useMemo(() => getComparisonStats(route), [route]);

  // Mapped stops for the StopMarkersLayer
  const mappedStops = useMemo(
    () =>
      stops
        .filter((s) => s.lat != null && s.lng != null)
        .map((s) => ({
          lat: s.lat!,
          lng: s.lng!,
          sequence: s.sequence,
          status: s.status,
          address: s.address,
        })),
    [stops],
  );

  // Auto-fit bounds
  useEffect(() => {
    if (mappedStops.length > 0) {
      mapRef.current?.fitBounds(mappedStops);
    }
  }, [mappedStops]);

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-full bg-slate-900">
        <div className="text-slate-500">Cargando analisis de ruta...</div>
      </div>
    );
  }

  if (error || !route) {
    return (
      <div className="flex items-center justify-center h-full bg-slate-900">
        <div className="text-red-500">
          {error ? `Error: ${error.message}` : 'Ruta no encontrada'}
        </div>
      </div>
    );
  }

  const metrics = route.metrics as Record<string, number> | undefined;

  return (
    <div className="relative flex h-full w-full overflow-hidden">
      {/* Sidebar */}
      <div className="absolute top-0 left-0 bottom-0 z-[999] w-80 flex flex-col bg-slate-900/95 backdrop-blur-xl border-r border-slate-700/50">
        {/* Back link */}
        <Link
          to="/app/admin/fleet-map"
          className="flex-shrink-0 flex items-center gap-2 px-5 pt-4 pb-2 text-slate-400 hover:text-white transition-colors text-sm font-medium"
        >
          <svg
            className="w-4 h-4"
            fill="none"
            viewBox="0 0 24 24"
            strokeWidth={2}
            stroke="currentColor"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"
            />
          </svg>
          Volver
        </Link>

        {/* Route header */}
        <div className="flex-shrink-0 px-5 pt-2 pb-3">
          <div className="flex items-center gap-2.5 mb-4">
            <div className="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center">
              <svg
                className="w-5 h-5 text-white"
                fill="none"
                viewBox="0 0 24 24"
                strokeWidth={1.5}
                stroke="currentColor"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z"
                />
              </svg>
            </div>
            <div>
              <h1 className="text-white font-bold text-base tracking-tight">
                {route.name}
              </h1>
              <p className="text-slate-500 text-[10px] uppercase tracking-widest">
                Analisis
              </p>
            </div>
          </div>

          {/* Status badge */}
          {route.status && (
            <span
              className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                route.status === 'DONE'
                  ? 'bg-emerald-500/20 text-emerald-400'
                  : route.status === 'ACTIVE'
                    ? 'bg-amber-500/20 text-amber-400'
                    : route.status === 'PLANNED'
                      ? 'bg-blue-500/20 text-blue-400'
                      : 'bg-slate-500/20 text-slate-400'
              }`}
            >
              {route.status}
            </span>
          )}
        </div>

        {/* Scrollable content */}
        <div className="flex-1 overflow-y-auto px-5 pb-4 space-y-4">
          {/* Comparison metrics */}
          {comparison && (
            <div className="space-y-2">
              <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                Comparacion
              </p>
              <div className="grid grid-cols-2 gap-2">
                <MetricCard
                  label="Dist. planificada"
                  value={`${comparison.plannedDistanceKm.toFixed(1)} km`}
                  color="blue"
                />
                <MetricCard
                  label="Dist. ejecutada"
                  value={`${comparison.actualDistanceKm.toFixed(1)} km`}
                  color="red"
                />
                <MetricCard
                  label="Desviacion"
                  value={`${comparison.deviationKm.toFixed(1)} km`}
                  color={comparison.deviationKm > 5 ? 'amber' : 'green'}
                />
                <MetricCard
                  label="Dif. tiempo"
                  value={`${comparison.extraTimeMinutes > 0 ? '+' : ''}${comparison.extraTimeMinutes.toFixed(0)} min`}
                  color={
                    Math.abs(comparison.extraTimeMinutes) > 15
                      ? 'amber'
                      : 'green'
                  }
                />
              </div>
            </div>
          )}

          {/* Route details */}
          <div className="bg-slate-800/60 rounded-lg p-3 border border-slate-700/40">
            <div className="text-[10px] text-slate-500 uppercase tracking-wider mb-2">
              Detalles
            </div>
            <div className="space-y-1.5 text-xs">
              {route.vehicleName && (
                <DetailRow label="Vehiculo" value={route.vehicleName} />
              )}
              {route.driverName && (
                <DetailRow label="Transportista" value={route.driverName} />
              )}
              <DetailRow label="Paradas" value={String(stops.length)} />
            </div>
          </div>

          {/* Optimization metrics */}
          <div>
            <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500 mb-2">
              Optimizacion
            </p>
            <RouteMetricsPanel
              metrics={
                metrics
                  ? {
                      distanceBeforeKm: metrics.distanceBeforeKm,
                      distanceAfterKm: metrics.distanceAfterKm,
                      savingsPercent: metrics.savingsPercent,
                      drivingTimeMinutes: metrics.drivingTimeMinutes,
                      deliveryTimeMinutes: metrics.deliveryTimeMinutes,
                      totalTimeMinutes: metrics.totalTimeMinutes,
                    }
                  : undefined
              }
            />
          </div>

          {/* Legend */}
          <div className="border-t border-slate-700/50 pt-4">
            <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500 mb-3">
              Leyenda
            </p>
            <div className="space-y-2">
              <div className="flex items-center gap-2">
                <div className="w-6 h-[3px] rounded-full bg-blue-500" />
                <span className="text-xs text-slate-400">
                  Ruta planificada
                </span>
              </div>
              <div className="flex items-center gap-2">
                <div
                  className="w-6 h-[3px] rounded-full bg-red-500"
                  style={{
                    backgroundImage:
                      'repeating-linear-gradient(90deg, #ef4444 0, #ef4444 4px, transparent 4px, transparent 8px)',
                  }}
                />
                <span className="text-xs text-slate-400">Ruta ejecutada</span>
              </div>
            </div>
          </div>

          {/* Stop list */}
          <div className="border-t border-slate-700/50 pt-4">
            <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500 mb-3">
              Paradas ({stops.length})
            </p>
            <div className="space-y-1">
              {stops.map((stop) => (
                <div
                  key={stop.sequence}
                  className="flex items-center gap-2 bg-slate-800/50 rounded-lg px-3 py-2 border border-slate-700/30"
                >
                  <span
                    className={`flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold text-white ${
                      stop.status === 'DELIVERED'
                        ? 'bg-emerald-500'
                        : stop.status === 'EXCEPTION'
                          ? 'bg-red-500'
                          : stop.status === 'SKIPPED'
                            ? 'bg-slate-500'
                            : 'bg-blue-500'
                    }`}
                  >
                    {stop.isOrigin ? 'O' : stop.sequence}
                  </span>
                  <div className="min-w-0 flex-1">
                    <div className="text-xs text-slate-200 truncate">
                      {stop.address}
                    </div>
                    <div className="text-[10px] text-slate-500">
                      {stop.status}
                      {stop.exceptionCode && ` - ${stop.exceptionCode}`}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>

      {/* Top header bar */}
      <div
        className="absolute top-0 left-80 right-0 z-[1000] h-14 flex items-center justify-between px-6"
        style={{
          background:
            'linear-gradient(to bottom, rgba(15,23,42,0.85) 0%, rgba(15,23,42,0.4) 70%, transparent 100%)',
        }}
      >
        <h2 className="text-white font-semibold text-sm">
          Analisis: {route.name}
        </h2>
        <div className="flex items-center gap-3">
          <span className="text-slate-400 text-xs">
            {stops.length} paradas
          </span>
        </div>
      </div>

      {/* Map */}
      <div className="absolute inset-0">
        <MapCanvas ref={mapRef}>
          {/* Planned route polyline (blue, solid) */}
          {route.polyline && (
            <RoutePolylineLayer
              id={`analysis-planned-${route.publicId}`}
              polyline={route.polyline}
              color="#3b82f6"
            />
          )}

          {/* Actual route polyline (red, comparison) */}
          {route.comparisonPolyline && (
            <RoutePolylineLayer
              id={`analysis-actual-${route.publicId}`}
              polyline={route.comparisonPolyline}
              color="#ef4444"
            />
          )}

          {/* Stop markers */}
          <StopMarkersLayer stops={mappedStops} keyPrefix="analysis-" />
        </MapCanvas>
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
  color: 'blue' | 'red' | 'amber' | 'green';
}) {
  const colorClasses = {
    blue: 'bg-blue-500/10 text-blue-400',
    red: 'bg-red-500/10 text-red-400',
    amber: 'bg-amber-500/10 text-amber-400',
    green: 'bg-emerald-500/10 text-emerald-400',
  };

  return (
    <div className="bg-slate-800/60 rounded-lg p-2.5 border border-slate-700/40">
      <div className="text-[10px] text-slate-500 mb-1">{label}</div>
      <div className={`text-sm font-bold ${colorClasses[color]}`}>{value}</div>
    </div>
  );
}

function DetailRow({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between">
      <span className="text-slate-500">{label}</span>
      <span className="text-slate-300 font-medium">{value}</span>
    </div>
  );
}
