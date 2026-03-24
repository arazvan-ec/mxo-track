import { STOP_STATUS_COLORS } from '@/components/maps/shared/colors';

interface Stop {
  sequence: number;
  address: string;
  status: string;
  recipientName?: string;
  etaTime?: string;
  deliveredAt?: string;
  isOrigin?: boolean;
}

interface Props {
  stops: Stop[];
  selectedSequence?: number | null;
  onStopClick?: (sequence: number) => void;
  showEta?: boolean;
  maxItems?: number;
}

const normalItemStyle = {
  backgroundColor: 'color-mix(in srgb, var(--color-surface-elevated) 50%, transparent)',
  borderColor: 'var(--color-border-subtle)',
};

const selectedItemStyle = {
  backgroundColor: 'var(--color-accent-muted)',
  borderColor: 'var(--color-border-accent)',
};

/**
 * Shared stop list panel — used in route detail, customer, and driver views.
 * Renders a scrollable list of stops with status badges and optional ETAs.
 */
export function StopListPanel({ stops, selectedSequence, onStopClick, showEta = false, maxItems }: Props) {
  const nonOriginStops = stops.filter((s) => !s.isOrigin);
  const visibleStops = maxItems != null ? nonOriginStops.slice(0, maxItems) : nonOriginStops;

  if (nonOriginStops.length === 0) {
    return <div className="text-center py-8 text-sm" style={{ color: 'var(--color-text-secondary)' }}>No stops</div>;
  }

  return (
    <div className="space-y-1">
      {visibleStops.map((stop) => {
        const color = STOP_STATUS_COLORS[stop.status] ?? STOP_STATUS_COLORS.PENDING;
        const isSelected = selectedSequence === stop.sequence;

        return (
          <button
            key={stop.sequence}
            onClick={() => onStopClick?.(stop.sequence)}
            className="w-full text-left p-2.5 rounded-lg transition-all border"
            style={isSelected ? selectedItemStyle : normalItemStyle}
          >
            <div className="flex items-center gap-2">
              {/* Sequence badge */}
              <div
                className="flex-shrink-0 w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold text-white"
                style={{ backgroundColor: color }}
              >
                {stop.sequence}
              </div>

              <div className="flex-1 min-w-0">
                <div className="text-sm truncate" style={{ color: 'var(--color-text-primary)' }}>{stop.address}</div>
                <div className="flex items-center gap-2 text-[11px]">
                  {stop.recipientName && (
                    <span className="truncate" style={{ color: 'var(--color-text-secondary)' }}>{stop.recipientName}</span>
                  )}
                  <span
                    className="font-medium uppercase"
                    style={{ color }}
                  >
                    {stop.status}
                  </span>
                </div>
              </div>

              {/* ETA or delivered time */}
              <div className="flex-shrink-0 text-right text-[10px]">
                {stop.deliveredAt && (
                  <div style={{ color: 'var(--color-success)' }}>{stop.deliveredAt}</div>
                )}
                {showEta && stop.etaTime && !stop.deliveredAt && (
                  <div style={{ color: 'var(--color-text-muted)' }}>ETA {stop.etaTime}</div>
                )}
              </div>
            </div>
          </button>
        );
      })}
      {nonOriginStops.length > visibleStops.length && (
        <div className="text-[10px] text-center py-1" style={{ color: 'var(--color-text-muted)' }}>
          +{nonOriginStops.length - visibleStops.length} more
        </div>
      )}
    </div>
  );
}
