import { useState, type ReactNode } from 'react';
import { NavigationSidebar } from './NavigationSidebar';

interface DualMenuShellProps {
  /** The page-specific data sidebar content */
  dataSidebar?: ReactNode;
  /** Width class for the data sidebar (default: 'w-80') */
  dataSidebarWidth?: string;
  /** Extra CSS classes for the data sidebar wrapper */
  dataSidebarClassName?: string;
  /** Main content (map, etc.) */
  children: ReactNode;
}

/**
 * Shell component with an overlay navigation sidebar and an inline data sidebar:
 *
 * 1. Navigation sidebar — overlay (same pattern as Twig pages), triggered by hamburger
 * 2. Data sidebar (inline, left) — page-specific content (metrics, stops, filters, etc.)
 *
 * When the data sidebar is collapsed, expand buttons appear on the map.
 */
export function DualMenuShell({
  dataSidebar,
  dataSidebarWidth = 'w-80',
  dataSidebarClassName = '',
  children,
}: DualMenuShellProps) {
  const [navOpen, setNavOpen] = useState(false);
  const [dataOpen, setDataOpen] = useState(true);

  return (
    <div className="flex h-screen w-full bg-slate-900">
      {/* Navigation sidebar — overlay (consistent with Twig pages) */}
      {navOpen && (
        <NavigationSidebar
          mode="overlay"
          onClose={() => setNavOpen(false)}
        />
      )}

      {/* Data sidebar (collapsible, inline) */}
      {dataSidebar && dataOpen && (
        <aside
          className={`${dataSidebarWidth} flex-shrink-0 flex flex-col overflow-hidden border-r border-slate-700 bg-slate-900 ${dataSidebarClassName}`}
        >
          {/* Header with hamburger + collapse */}
          <div className="flex items-center gap-2 px-3 py-2 border-b border-slate-700/50 flex-shrink-0">
            <button
              type="button"
              onClick={() => setNavOpen(true)}
              className="text-slate-400 hover:text-white transition-colors p-1 rounded hover:bg-slate-700"
              title="Abrir navegacion"
            >
              <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
              </svg>
            </button>
            <span className="text-[11px] font-semibold uppercase tracking-wider text-slate-400 flex-1">
              Datos
            </span>
            <button
              type="button"
              onClick={() => setDataOpen(false)}
              className="text-slate-400 hover:text-white transition-colors p-1 rounded hover:bg-slate-700"
              title="Minimizar panel de datos"
            >
              <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M18.75 19.5l-7.5-7.5 7.5-7.5m-6 15L5.25 12l7.5-7.5" />
              </svg>
            </button>
          </div>
          <div className="flex-1 overflow-y-auto">
            {dataSidebar}
          </div>
        </aside>
      )}

      {/* Main area */}
      <div className="flex-1 relative overflow-hidden">
        {/* Expand buttons when data sidebar is collapsed */}
        {dataSidebar && !dataOpen && (
          <div className="absolute top-3 left-3 z-20 flex items-center gap-1.5">
            <button
              type="button"
              onClick={() => setNavOpen(true)}
              className="flex items-center justify-center w-9 h-9 rounded-lg border bg-slate-800/90 border-slate-600 text-slate-300 hover:bg-slate-700 hover:text-white transition-colors"
              title="Abrir navegacion"
            >
              <svg className="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
              </svg>
            </button>
            <button
              type="button"
              onClick={() => setDataOpen(true)}
              className="flex items-center justify-center w-9 h-9 rounded-lg border bg-slate-800/90 border-slate-600 text-slate-300 hover:bg-slate-700 hover:text-white transition-colors"
              title="Abrir panel de datos"
            >
              <svg className="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
              </svg>
            </button>
          </div>
        )}

        {/* Hamburger on map when no data sidebar exists */}
        {!dataSidebar && (
          <div className="absolute top-3 left-3 z-20">
            <button
              type="button"
              onClick={() => setNavOpen(true)}
              className="flex items-center justify-center w-9 h-9 rounded-lg border bg-slate-800/90 border-slate-600 text-slate-300 hover:bg-slate-700 hover:text-white transition-colors"
              title="Abrir navegacion"
            >
              <svg className="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
              </svg>
            </button>
          </div>
        )}

        {children}
      </div>
    </div>
  );
}
