import type { TestRoutingMetrics } from '@/api/hooks/useTestRoutingData';

interface MetricPairsProps {
  metrics: TestRoutingMetrics;
  expanded?: boolean;
}

function formatDuration(minutes: number): string {
  if (minutes < 60) return `${minutes}m`;
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  return m > 0 ? `${h}h ${m}m` : `${h}h`;
}

export function MetricPairs({ metrics, expanded = false }: MetricPairsProps) {
  const distanceSavedKm = Math.abs(
    metrics.distanceBeforeKm - metrics.distanceAfterKm,
  ).toFixed(1);
  const timeSavedMin = Math.abs(
    metrics.durationBeforeMinutes - metrics.totalDurationMinutes,
  );

  return (
    <div className="grid grid-cols-3 gap-2 px-4 pb-3">
      {/* Scope pair */}
      <div className="bg-slate-800/60 rounded-lg p-3">
        <p className="text-lg font-bold text-slate-200">
          {metrics.routeCount}{' '}
          <span className="text-sm font-medium">rutas</span>
        </p>
        <p className="text-xs text-slate-400">
          {metrics.stopCount} paradas
        </p>
      </div>

      {/* Distance pair */}
      <div className="bg-slate-800/60 rounded-lg p-3">
        <p className="text-lg font-bold text-blue-400">
          {metrics.distanceAfterKm}{' '}
          <span className="text-sm font-medium">km</span>
        </p>
        <p className="text-xs text-emerald-400">
          <span className="inline-block mr-0.5">▼</span>
          {metrics.savedPercent}%
          {expanded && (
            <span className="text-slate-500"> · -{distanceSavedKm} km</span>
          )}
        </p>
      </div>

      {/* Time pair */}
      <div className="bg-slate-800/60 rounded-lg p-3">
        <p className="text-lg font-bold text-purple-400">
          {formatDuration(metrics.totalDurationMinutes)}
        </p>
        <p className="text-xs text-emerald-400">
          <span className="inline-block mr-0.5">▼</span>
          {metrics.timeSavedPercent}%
          {expanded && (
            <span className="text-slate-500">
              {' '}
              · -{formatDuration(timeSavedMin)}
            </span>
          )}
        </p>
      </div>
    </div>
  );
}
