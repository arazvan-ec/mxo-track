import type { WidgetProps } from './types';

interface RouteComparisonData {
  metrics?: {
    distanceBeforeKm?: number;
    distanceAfterKm?: number;
    savedPercent?: number;
    savingsPercent?: number;
    durationBeforeMinutes?: number;
    totalDurationMinutes?: number;
    timeSavedPercent?: number;
  };
  /** Analysis page comparison data */
  comparison?: {
    plannedDistanceKm: number;
    actualDistanceKm: number;
    deviationKm: number;
    extraTimeMinutes: number;
  };
}

function formatDuration(minutes: number): string {
  if (minutes < 60) return `${minutes}m`;
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  return m > 0 ? `${h}h ${m}m` : `${h}h`;
}

export function RouteComparisonWidget({ data }: WidgetProps) {
  const { metrics, comparison } = data as RouteComparisonData;

  // Analysis comparison mode (planned vs actual)
  if (comparison) {
    return (
      <div className="px-4 pb-3">
        <div className="theme-card overflow-hidden">
          <div className="px-3 py-2 border-b">
            <h4 className="text-[10px] font-semibold text-[var(--color-text-muted)] uppercase">
              Planned vs Actual
            </h4>
          </div>
          <div className="grid grid-cols-2 gap-2 p-3">
            <ComparisonCard
              label="Dist. planificada"
              value={`${comparison.plannedDistanceKm.toFixed(1)} km`}
              color="blue"
            />
            <ComparisonCard
              label="Dist. ejecutada"
              value={`${comparison.actualDistanceKm.toFixed(1)} km`}
              color="red"
            />
            <ComparisonCard
              label="Desviacion"
              value={`${comparison.deviationKm.toFixed(1)} km`}
              color={comparison.deviationKm > 5 ? 'amber' : 'green'}
            />
            <ComparisonCard
              label="Dif. tiempo"
              value={`${comparison.extraTimeMinutes > 0 ? '+' : ''}${comparison.extraTimeMinutes.toFixed(0)} min`}
              color={Math.abs(comparison.extraTimeMinutes) > 15 ? 'amber' : 'green'}
            />
          </div>
        </div>
      </div>
    );
  }

  // Optimization comparison mode (before vs after)
  if (!metrics) return null;

  return (
    <div className="px-4 pb-3">
      <div className="bg-slate-800/60 rounded-lg overflow-hidden">
        <div className="px-3 py-2 border-b">
          <h4 className="text-[10px] font-semibold text-[var(--color-text-muted)] uppercase">
            Original vs Optimized
          </h4>
        </div>
        <table className="w-full text-xs">
          <thead>
            <tr className="text-[var(--color-text-muted)]">
              <th className="px-3 py-1.5 text-left font-medium" />
              <th className="px-3 py-1.5 text-right font-medium">Before</th>
              <th className="px-3 py-1.5 text-right font-medium">After</th>
              <th className="px-3 py-1.5 text-right font-medium">Saved</th>
            </tr>
          </thead>
          <tbody className="text-[var(--color-text-primary)]">
            <tr className="border-t border-[var(--color-border)]">
              <td className="px-3 py-1.5 text-[var(--color-text-muted)]">Distance</td>
              <td className="px-3 py-1.5 text-right">{metrics.distanceBeforeKm} km</td>
              <td className="px-3 py-1.5 text-right text-blue-400">{metrics.distanceAfterKm} km</td>
              <td className="px-3 py-1.5 text-right text-emerald-400">{metrics.savedPercent ?? metrics.savingsPercent}%</td>
            </tr>
            {metrics.durationBeforeMinutes != null && (
              <tr className="border-t border-[var(--color-border)]">
                <td className="px-3 py-1.5 text-[var(--color-text-muted)]">Time</td>
                <td className="px-3 py-1.5 text-right">{formatDuration(metrics.durationBeforeMinutes)}</td>
                <td className="px-3 py-1.5 text-right text-purple-400">
                  {formatDuration(metrics.totalDurationMinutes ?? 0)}
                </td>
                <td className="px-3 py-1.5 text-right text-emerald-400">{metrics.timeSavedPercent}%</td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function ComparisonCard({
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
    <div className="theme-card p-2.5">
      <div className="text-[10px] mb-1" style={{ color: 'var(--color-text-muted)' }}>{label}</div>
      <div className={`text-sm font-bold ${colorClasses[color]}`}>{value}</div>
    </div>
  );
}
