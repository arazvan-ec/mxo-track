import type { WidgetProps } from './types';

interface DriverInfoData {
  driverName?: string;
  deliveredCount?: number;
  totalCount?: number;
  currentStop?: {
    address: string;
    etaTime?: string;
  } | null;
}

export function DriverInfoWidget({ data, expanded }: WidgetProps) {
  const { driverName, deliveredCount, totalCount, currentStop } = data as DriverInfoData;
  if (!driverName && deliveredCount == null) return null;

  const progress = totalCount && totalCount > 0 ? (deliveredCount ?? 0) / totalCount : 0;

  return (
    <div className="px-4 pb-3">
      <div
        className="rounded-lg p-3 border"
        style={{
          backgroundColor: 'color-mix(in srgb, var(--color-surface-elevated) 60%, transparent)',
          borderColor: 'var(--color-border-subtle)',
        }}
      >
        <div className="text-[10px] uppercase tracking-wider mb-2" style={{ color: 'var(--color-text-muted)' }}>
          Driver
        </div>

        {driverName && (
          <div className="text-sm font-medium mb-2" style={{ color: 'var(--color-text-primary)' }}>
            {driverName}
          </div>
        )}

        {totalCount != null && totalCount > 0 && (
          <>
            <div className="flex items-center justify-between mb-1">
              <span className="text-[10px] uppercase" style={{ color: 'var(--color-text-muted)' }}>
                Progress
              </span>
              <span className="text-xs font-medium" style={{ color: 'var(--color-text-primary)' }}>
                {deliveredCount ?? 0}/{totalCount}
              </span>
            </div>
            <div className="w-full h-2 rounded-full overflow-hidden" style={{ backgroundColor: 'var(--color-surface-elevated)' }}>
              <div
                className="h-full rounded-full transition-all"
                style={{ width: `${progress * 100}%`, backgroundColor: 'var(--color-success)' }}
              />
            </div>
          </>
        )}

        {expanded && currentStop && (
          <div className="mt-2 text-xs" style={{ color: 'var(--color-text-secondary)' }}>
            Next: <span style={{ color: 'var(--color-text-primary)' }}>{currentStop.address}</span>
            {currentStop.etaTime && (
              <span className="ml-1" style={{ color: 'var(--color-accent)' }}>ETA {currentStop.etaTime}</span>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
