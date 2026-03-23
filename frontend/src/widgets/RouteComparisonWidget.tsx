import type { TestRoutingMetrics } from '@/api/hooks/useTestRoutingData';
import type { WidgetProps } from './types';

interface RouteComparisonData {
  metrics: TestRoutingMetrics;
}

function formatDuration(minutes: number): string {
  if (minutes < 60) return `${minutes}m`;
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  return m > 0 ? `${h}h ${m}m` : `${h}h`;
}

export function RouteComparisonWidget({ data }: WidgetProps) {
  const { metrics } = data as RouteComparisonData;
  if (!metrics) return null;

  return (
    <div className="px-4 pb-3">
      <div className="bg-slate-800/60 rounded-lg overflow-hidden">
        <div className="px-3 py-2 border-b border-slate-700">
          <h4 className="text-[10px] font-semibold text-slate-400 uppercase">
            Original vs Optimized
          </h4>
        </div>
        <table className="w-full text-xs">
          <thead>
            <tr className="text-slate-400">
              <th className="px-3 py-1.5 text-left font-medium" />
              <th className="px-3 py-1.5 text-right font-medium">Before</th>
              <th className="px-3 py-1.5 text-right font-medium">After</th>
              <th className="px-3 py-1.5 text-right font-medium">Saved</th>
            </tr>
          </thead>
          <tbody className="text-slate-200">
            <tr className="border-t border-slate-700/50">
              <td className="px-3 py-1.5 text-slate-400">Distance</td>
              <td className="px-3 py-1.5 text-right">{metrics.distanceBeforeKm} km</td>
              <td className="px-3 py-1.5 text-right text-blue-400">{metrics.distanceAfterKm} km</td>
              <td className="px-3 py-1.5 text-right text-emerald-400">{metrics.savedPercent}%</td>
            </tr>
            <tr className="border-t border-slate-700/50">
              <td className="px-3 py-1.5 text-slate-400">Time</td>
              <td className="px-3 py-1.5 text-right">{formatDuration(metrics.durationBeforeMinutes)}</td>
              <td className="px-3 py-1.5 text-right text-purple-400">
                {formatDuration(metrics.totalDurationMinutes)}
              </td>
              <td className="px-3 py-1.5 text-right text-emerald-400">{metrics.timeSavedPercent}%</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  );
}
