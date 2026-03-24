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

const cardStyle = {
  backgroundColor: 'color-mix(in srgb, var(--color-surface-elevated) 60%, transparent)',
  borderColor: 'var(--color-border-subtle)',
};

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
        <div className="rounded-lg p-3 border" style={cardStyle}>
          <div className="text-[10px] uppercase tracking-wider mb-2" style={{ color: 'var(--color-text-muted)' }}>Distance</div>
          <div className="grid grid-cols-3 gap-2 text-center">
            <div>
              <div className="text-sm font-medium" style={{ color: 'var(--color-text-secondary)' }}>
                {metrics.distanceBeforeKm.toFixed(1)} km
              </div>
              <div className="text-[9px]" style={{ color: 'var(--color-text-muted)' }}>Before</div>
            </div>
            <div>
              <div className="text-sm font-medium" style={{ color: 'var(--color-text-primary)' }}>
                {metrics.distanceAfterKm.toFixed(1)} km
              </div>
              <div className="text-[9px]" style={{ color: 'var(--color-text-muted)' }}>After</div>
            </div>
            <div>
              <div
                className="text-sm font-bold"
                style={{ color: (metrics.savingsPercent ?? 0) > 0 ? 'var(--color-success)' : 'var(--color-text-secondary)' }}
              >
                {(metrics.savingsPercent ?? 0).toFixed(1)}%
              </div>
              <div className="text-[9px]" style={{ color: 'var(--color-text-muted)' }}>Saved</div>
            </div>
          </div>
        </div>
      )}

      {/* Timing breakdown */}
      {hasTiming && (
        <div className="rounded-lg p-3 border" style={cardStyle}>
          <div className="text-[10px] uppercase tracking-wider mb-2" style={{ color: 'var(--color-text-muted)' }}>Timing</div>
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
      <span style={{ color: 'var(--color-text-muted)' }}>{label}</span>
      <span
        className={bold ? 'font-medium' : ''}
        style={{ color: bold ? 'var(--color-text-primary)' : 'var(--color-text-secondary)' }}
      >
        {formatted}
      </span>
    </div>
  );
}
