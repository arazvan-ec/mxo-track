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
      <span className="font-medium uppercase text-slate-400 flex-shrink-0">
        {status}
      </span>
      <span className="text-slate-300 flex-shrink-0">
        {deliveredCount}/{totalCount}
      </span>
      {remainingDistance && (
        <>
          <span className="text-slate-600">&middot;</span>
          <span className="text-slate-400 truncate">{remainingDistance}</span>
        </>
      )}
      {nextEta && (
        <>
          <span className="text-slate-600">&middot;</span>
          <span className="text-blue-400 truncate">ETA {nextEta}</span>
        </>
      )}
    </div>
  );
}
