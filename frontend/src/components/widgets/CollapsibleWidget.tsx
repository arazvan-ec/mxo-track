import { useState, useCallback, type ReactNode } from 'react';
import { useUserPreferencesContext } from '@/context/UserPreferencesContext';

interface CollapsibleWidgetProps {
  title: string;
  icon?: ReactNode;
  summary?: ReactNode;
  storageKey: string;
  defaultExpanded?: boolean;
  /**
   * Override the initial expanded state. Takes priority over user preferences
   * and `defaultExpanded`, but localStorage (user's per-widget toggle) wins if set.
   *
   * Resolution order (first non-empty wins):
   *   1. localStorage entry for this storageKey (user previously toggled)
   *   2. `initialMode` prop (if set)
   *   3. UserPreferencesContext `widget_default_mode` (if context present)
   *   4. `defaultExpanded` prop (default: true)
   */
  initialMode?: 'expanded' | 'collapsed';
  children: ReactNode;
}

function resolveInitialExpanded(
  storageKey: string,
  defaultExpanded: boolean,
  initialMode: 'expanded' | 'collapsed' | undefined,
  contextMode: 'expanded' | 'collapsed' | undefined,
): boolean {
  // 1. localStorage wins (user explicitly toggled this widget)
  try {
    const stored = localStorage.getItem(storageKey);
    if (stored === 'false') return false;
    if (stored === 'true') return true;
  } catch {
    // localStorage unavailable
  }

  // 2. initialMode prop
  if (initialMode) return initialMode === 'expanded';

  // 3. User preference from context
  if (contextMode) return contextMode === 'expanded';

  // 4. defaultExpanded prop
  return defaultExpanded;
}

export function CollapsibleWidget({
  title,
  icon,
  summary,
  storageKey,
  defaultExpanded = true,
  initialMode,
  children,
}: CollapsibleWidgetProps) {
  const { preferences } = useUserPreferencesContext();
  const contextMode = preferences?.widget_default_mode;

  const [expanded, setExpanded] = useState(() =>
    resolveInitialExpanded(storageKey, defaultExpanded, initialMode, contextMode),
  );

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
    <div className="theme-card overflow-hidden">
      {/* Header — always visible, clickable to toggle */}
      <button
        type="button"
        onClick={toggle}
        className="flex w-full items-center justify-between px-4 py-3 text-left transition-colors"
        style={{ borderBottom: expanded ? `1px solid var(--color-border)` : 'none' }}
      >
        <div className="flex items-center gap-3 min-w-0 flex-1">
          {icon && <span style={{ color: 'var(--color-text-muted)' }}>{icon}</span>}
          <h2 className="text-xs font-semibold uppercase tracking-wider shrink-0" style={{ color: 'var(--color-text-muted)' }}>
            {title}
          </h2>
          {summary && (
            <div className="min-w-0 flex-1 text-right text-sm" style={{ color: 'var(--color-text-primary)' }}>
              {summary}
            </div>
          )}
        </div>
        <svg
          className={`h-4 w-4 transition-transform duration-200 ${expanded ? 'rotate-180' : ''}`}
          style={{ color: 'var(--color-text-muted)' }}
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
        <div className="px-4 pb-4 pt-3">{children}</div>
      </div>
    </div>
  );
}
