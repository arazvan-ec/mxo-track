interface RouteSummaryBarProps {
  status: string;
  deliveredCount: number;
  totalCount: number;
  remainingDistance?: string;
  nextEta?: string;
}

export function RouteSummaryBar({ status, deliveredCount, totalCount, remainingDistance, nextEta }: RouteSummaryBarProps) {
  return (
    <div className="flex items-center gap-2 text-[11px] overflow-hidden">
      <span className="font-medium uppercase flex-shrink-0" style={{ color: 'var(--color-text-secondary)' }}>
        {status}
      </span>
      <span className="flex-shrink-0" style={{ color: 'var(--color-text-primary)' }}>
        {deliveredCount}/{totalCount}
      </span>
      {remainingDistance && (
        <>
          <span style={{ color: 'var(--color-text-muted)' }}>&middot;</span>
          <span className="truncate" style={{ color: 'var(--color-text-secondary)' }}>{remainingDistance}</span>
        </>
      )}
      {nextEta && (
        <>
          <span style={{ color: 'var(--color-text-muted)' }}>&middot;</span>
          <span className="truncate" style={{ color: 'var(--color-accent)' }}>ETA {nextEta}</span>
        </>
      )}
    </div>
  );
}
