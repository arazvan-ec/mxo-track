import { StopListPanel } from '@/components/panels/StopListPanel';
import type { WidgetProps } from './types';

interface StopListData {
  stops?: Array<{
    sequence: number;
    address: string;
    status: string;
    recipientName?: string;
    etaTime?: string;
    deliveredAt?: string;
    isOrigin?: boolean;
  }>;
  selectedSequence?: number | null;
  onStopClick?: (sequence: number) => void;
  showEta?: boolean;
  maxItems?: number;
}

export function StopListWidget({ data, expanded }: WidgetProps) {
  const { stops, selectedSequence, onStopClick, showEta } = data as StopListData;
  if (!stops || stops.length === 0) return null;

  return (
    <div className="px-4 pb-3">
      <div className="text-[10px] uppercase tracking-wider mb-2" style={{ color: 'var(--color-text-muted)' }}>
        Stops ({stops.filter((s) => !s.isOrigin).length})
      </div>
      <StopListPanel
        stops={stops}
        selectedSequence={selectedSequence}
        onStopClick={onStopClick}
        showEta={showEta}
        maxItems={expanded ? undefined : 3}
      />
    </div>
  );
}
