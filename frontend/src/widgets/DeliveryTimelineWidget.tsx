import type { WidgetProps } from './types';

interface TimelineEvent {
  time: string;
  label: string;
  status: 'completed' | 'active' | 'pending';
}

interface DeliveryTimelineData {
  events?: TimelineEvent[];
  /** Build timeline from stops when events not available */
  stops?: Array<{
    sequence: number;
    address: string;
    status: string;
    deliveredAt?: string;
    etaTime?: string;
    isOrigin?: boolean;
  }>;
}

const STATUS_STYLES: Record<string, { dot: string; line: string; text: string }> = {
  completed: { dot: 'var(--color-success)', line: 'var(--color-success)', text: 'var(--color-text-primary)' },
  active: { dot: 'var(--color-accent)', line: 'var(--color-border-subtle)', text: 'var(--color-text-primary)' },
  pending: { dot: 'var(--color-border-subtle)', line: 'var(--color-border-subtle)', text: 'var(--color-text-muted)' },
};

function stopsToEvents(stops: DeliveryTimelineData['stops']): TimelineEvent[] {
  if (!stops) return [];
  return stops
    .filter((s) => !s.isOrigin)
    .map((s) => {
      let status: TimelineEvent['status'] = 'pending';
      if (s.status === 'DELIVERED' || s.status === 'delivered') status = 'completed';
      else if (s.status === 'EXCEPTION' || s.status === 'exception') status = 'completed';
      else if (s.status === 'SKIPPED' || s.status === 'skipped') status = 'completed';

      return {
        time: s.deliveredAt ?? s.etaTime ?? '',
        label: `#${s.sequence} ${s.address}`,
        status,
      };
    });
}

export function DeliveryTimelineWidget({ data, expanded }: WidgetProps) {
  const { events: rawEvents, stops } = data as DeliveryTimelineData;
  const events = rawEvents ?? stopsToEvents(stops);
  if (events.length === 0) return null;

  const visibleEvents = expanded ? events : events.slice(0, 5);

  return (
    <div className="px-4 pb-3">
      <div className="text-[10px] uppercase tracking-wider mb-2" style={{ color: 'var(--color-text-muted)' }}>
        Timeline
      </div>
      <div className="space-y-0">
        {visibleEvents.map((event, idx) => {
          const style = STATUS_STYLES[event.status] ?? STATUS_STYLES.pending;
          const isLast = idx === visibleEvents.length - 1;
          return (
            <div key={idx} className="flex gap-3">
              {/* Timeline column */}
              <div className="flex flex-col items-center flex-shrink-0">
                <div
                  className="w-2.5 h-2.5 rounded-full flex-shrink-0"
                  style={{ backgroundColor: style.dot }}
                />
                {!isLast && (
                  <div className="w-px flex-1 min-h-[20px]" style={{ backgroundColor: style.line }} />
                )}
              </div>
              {/* Content */}
              <div className="pb-3 min-w-0">
                <div className="text-xs truncate" style={{ color: style.text }}>
                  {event.label}
                </div>
                {event.time && (
                  <div className="text-[10px]" style={{ color: 'var(--color-text-muted)' }}>
                    {event.time}
                  </div>
                )}
              </div>
            </div>
          );
        })}
      </div>
      {events.length > visibleEvents.length && (
        <div className="text-[10px] text-center py-1" style={{ color: 'var(--color-text-muted)' }}>
          +{events.length - visibleEvents.length} more
        </div>
      )}
    </div>
  );
}
