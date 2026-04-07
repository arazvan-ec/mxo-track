import { useState, useCallback, type ReactNode } from 'react';

interface CollapsibleWidgetProps {
  title: string;
  icon?: ReactNode;
  storageKey: string;
  defaultExpanded?: boolean;
  children: ReactNode;
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

export function CollapsibleWidget({
  title,
  icon,
  storageKey,
  defaultExpanded = true,
  children,
}: CollapsibleWidgetProps) {
  const [expanded, setExpanded] = useState(() => getInitialExpanded(storageKey, defaultExpanded));

  const toggle = useCallback(() => {
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

  return (
    <div className="rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5 overflow-hidden">
      {/* Header — always visible, clickable to toggle */}
      <button
        type="button"
        onClick={toggle}
        className="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-gray-50 transition-colors"
      >
        <div className="flex items-center gap-2">
          {icon && <span className="text-gray-400">{icon}</span>}
          <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wider">{title}</h2>
        </div>
        <svg
          className={`h-4 w-4 text-gray-400 transition-transform duration-200 ${expanded ? 'rotate-180' : ''}`}
          fill="none"
          viewBox="0 0 24 24"
          strokeWidth={2}
          stroke="currentColor"
        >
          <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
        </svg>
      </button>

      {/* Collapsible content */}
      <div
        className={`transition-all duration-200 ease-in-out overflow-hidden ${
          expanded ? 'max-h-[2000px] opacity-100' : 'max-h-0 opacity-0'
        }`}
      >
        <div className="px-4 pb-4">{children}</div>
      </div>
    </div>
  );
}
