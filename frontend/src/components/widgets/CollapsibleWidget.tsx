import { useState, useCallback, type ReactNode } from 'react';

interface CollapsibleWidgetProps {
  title: string;
  icon?: ReactNode;
  storageKey: string;
  defaultExpanded?: boolean;
  supportsDetail?: boolean;
  defaultDetailed?: boolean;
  onDetailChange?: (detailed: boolean) => void;
  children: ReactNode | ((detailed: boolean) => ReactNode);
}

function getInitialExpanded(storageKey: string, defaultExpanded: boolean): boolean {
  try {
    const stored = localStorage.getItem(storageKey);
    if (stored === 'false') return false;
    if (stored === 'true') return true;
  } catch {
    // localStorage unavailable
  }
  return defaultExpanded;
}

function getInitialDetailed(storageKey: string, defaultDetailed: boolean): boolean {
  try {
    const stored = localStorage.getItem(`${storageKey}-detailed`);
    if (stored === 'false') return false;
    if (stored === 'true') return true;
  } catch {
    // localStorage unavailable
  }
  return defaultDetailed;
}

export function CollapsibleWidget({
  title,
  icon,
  storageKey,
  defaultExpanded = true,
  supportsDetail,
  defaultDetailed = false,
  onDetailChange,
  children,
}: CollapsibleWidgetProps) {
  const [expanded, setExpanded] = useState(() => getInitialExpanded(storageKey, defaultExpanded));
  const [detailed, setDetailed] = useState(() => getInitialDetailed(storageKey, defaultDetailed));

  const toggleExpanded = useCallback(() => {
    setExpanded((prev) => {
      const next = !prev;
      try {
        localStorage.setItem(storageKey, String(next));
      } catch {
        // localStorage unavailable
      }
      return next;
    });
  }, [storageKey]);

  const toggleDetailed = useCallback(() => {
    setDetailed((prev) => {
      const next = !prev;
      try {
        localStorage.setItem(`${storageKey}-detailed`, String(next));
      } catch {
        // localStorage unavailable
      }
      onDetailChange?.(next);
      return next;
    });
  }, [storageKey, onDetailChange]);

  const renderedChildren = typeof children === 'function' ? children(detailed) : children;

  return (
    <div className="theme-card overflow-hidden">
      {/* Header — always visible */}
      <div
        className="flex w-full items-center justify-between px-4 py-3"
        style={{ borderBottom: expanded ? `1px solid var(--color-border)` : 'none' }}
      >
        <div className="flex items-center gap-2">
          {icon && <span style={{ color: 'var(--color-text-muted)' }}>{icon}</span>}
          <h2 className="text-xs font-semibold uppercase tracking-wider" style={{ color: 'var(--color-text-muted)' }}>
            {title}
          </h2>
        </div>
        <div className="flex items-center gap-1">
          {supportsDetail && (
            <button
              type="button"
              onClick={toggleDetailed}
              aria-label="Toggle detail"
              className="inline-flex items-center justify-center rounded p-1 transition-colors hover:bg-black/10"
            >
              <svg
                className="h-4 w-4"
                style={{
                  color: 'var(--color-text-muted)',
                  transform: detailed ? 'scale(1.15)' : 'scale(1)',
                  transition: 'transform var(--dur-fast, 200ms) var(--ease-ios, ease-out)',
                }}
                fill="none"
                viewBox="0 0 24 24"
                strokeWidth={2}
                stroke="currentColor"
              >
                <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
              </svg>
            </button>
          )}
          <button
            type="button"
            onClick={toggleExpanded}
            aria-label="Toggle expand"
            className="inline-flex items-center justify-center rounded p-1 transition-colors hover:bg-black/10"
          >
            <svg
              className={`h-4 w-4 ${expanded ? 'rotate-180' : ''}`}
              style={{
                color: 'var(--color-text-muted)',
                transition: 'transform var(--dur-fast, 200ms) var(--ease-ios, ease-out)',
              }}
              fill="none"
              viewBox="0 0 24 24"
              strokeWidth={2}
              stroke="currentColor"
            >
              <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </button>
        </div>
      </div>

      {/* Collapsible content */}
      <div
        className="overflow-hidden"
        style={{
          maxHeight: expanded ? '2000px' : '0',
          opacity: expanded ? 1 : 0,
          transition: 'max-height var(--dur-fast, 200ms) var(--ease-ios, ease-in-out), opacity var(--dur-fast, 200ms) var(--ease-ios, ease-in-out)',
        }}
      >
        <div className="px-4 pb-4 pt-3">{renderedChildren}</div>
      </div>
    </div>
  );
}
