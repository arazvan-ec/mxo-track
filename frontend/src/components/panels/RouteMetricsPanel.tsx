interface Metrics {
  distanceBeforeKm?: number;
  distanceAfterKm?: number;
  savingsPercent?: number;
  drivingTimeMinutes?: number;
  deliveryTimeMinutes?: number;
  totalTimeMinutes?: number;
}

interface Props {
  metrics: Metrics | undefined;
}

/**
 * Route optimization metrics panel — admin only.
 * Shows distance before/after, savings %, timing breakdown.
 */
export function RouteMetricsPanel({ metrics }: Props) {
  if (!metrics) return null;

  const hasTiming = metrics.drivingTimeMinutes != null || metrics.totalTimeMinutes != null;

  return (
    <div className="space-y-3">
      {/* Distance */}
      {metrics.distanceBeforeKm != null && metrics.distanceAfterKm != null && (
        <div className="bg-slate-800/60 rounded-lg p-3 border border-slate-700/40">
          <div className="text-[10px] text-slate-500 uppercase tracking-wider mb-2">Distance</div>
          <div className="grid grid-cols-3 gap-2 text-center">
            <div>
              <div className="text-sm font-medium text-slate-400">
                {metrics.distanceBeforeKm.toFixed(1)} km
              </div>
              <div className="text-[9px] text-slate-600">Before</div>
            </div>
            <div>
              <div className="text-sm font-medium text-white">
                {metrics.distanceAfterKm.toFixed(1)} km
              </div>
              <div className="text-[9px] text-slate-600">After</div>
            </div>
            <div>
              <div className={`text-sm font-bold ${
                (metrics.savingsPercent ?? 0) > 0 ? 'text-emerald-400' : 'text-slate-400'
              }`}>
                {(metrics.savingsPercent ?? 0).toFixed(1)}%
              </div>
              <div className="text-[9px] text-slate-600">Saved</div>
            </div>
          </div>
        </div>
      )}

      {/* Timing breakdown */}
      {hasTiming && (
        <div className="bg-slate-800/60 rounded-lg p-3 border border-slate-700/40">
          <div className="text-[10px] text-slate-500 uppercase tracking-wider mb-2">Timing</div>
          <div className="space-y-1.5">
            {metrics.drivingTimeMinutes != null && (
              <TimingRow label="Driving" minutes={metrics.drivingTimeMinutes} />
            )}
            {metrics.deliveryTimeMinutes != null && (
              <TimingRow label="Delivery" minutes={metrics.deliveryTimeMinutes} />
            )}
            {metrics.totalTimeMinutes != null && (
              <TimingRow label="Total" minutes={metrics.totalTimeMinutes} bold />
            )}
          </div>
        </div>
      )}
    </div>
  );
}

function TimingRow({ label, minutes, bold }: { label: string; minutes: number; bold?: boolean }) {
  const h = Math.floor(minutes / 60);
  const m = Math.round(minutes % 60);
  const formatted = h > 0 ? `${h}h ${m}m` : `${m}m`;

  return (
    <div className="flex items-center justify-between text-xs">
      <span className="text-slate-500">{label}</span>
      <span className={bold ? 'text-white font-medium' : 'text-slate-300'}>
        {formatted}
      </span>
    </div>
  );
}
