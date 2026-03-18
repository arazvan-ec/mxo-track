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
}

/**
 * Shared stop list panel — used in route detail, customer, and driver views.
 * Renders a scrollable list of stops with status badges and optional ETAs.
 */
export function StopListPanel({ stops, selectedSequence, onStopClick, showEta = false }: Props) {
  const nonOriginStops = stops.filter((s) => !s.isOrigin);

  if (nonOriginStops.length === 0) {
    return <div className="text-center py-8 text-slate-600 text-sm">No stops</div>;
  }

  return (
    <div className="space-y-1">
      {nonOriginStops.map((stop) => {
        const color = STOP_STATUS_COLORS[stop.status] ?? STOP_STATUS_COLORS.PENDING;
        const isSelected = selectedSequence === stop.sequence;

        return (
          <button
            key={stop.sequence}
            onClick={() => onStopClick?.(stop.sequence)}
            className={`w-full text-left p-2.5 rounded-lg transition-all border ${
              isSelected
                ? 'bg-blue-600/20 border-blue-500/40'
                : 'bg-slate-800/50 border-slate-700/30 hover:bg-slate-800/80'
            }`}
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
                <div className="text-sm text-slate-200 truncate">{stop.address}</div>
                <div className="flex items-center gap-2 text-[11px]">
                  {stop.recipientName && (
                    <span className="text-slate-400 truncate">{stop.recipientName}</span>
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
                  <div className="text-emerald-400">{stop.deliveredAt}</div>
                )}
                {showEta && stop.etaTime && !stop.deliveredAt && (
                  <div className="text-slate-500">ETA {stop.etaTime}</div>
                )}
              </div>
            </div>
          </button>
        );
      })}
    </div>
  );
}
