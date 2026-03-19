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
 * Shell component that provides two independent hamburger menus for all SPA pages:
 *
 * 1. Navigation hamburger (left) — opens a slide-in sidebar with links to all app sections
 * 2. Data hamburger (right) — toggles the page-specific data sidebar (metrics, stops, filters, etc.)
 *
 * Both menus are independently collapsible.
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
      {/* Navigation sidebar (overlay) */}
      {navOpen && <NavigationSidebar onClose={() => setNavOpen(false)} />}

      {/* Data sidebar (collapsible, inline) */}
      {dataSidebar && dataOpen && (
        <aside
          className={`${dataSidebarWidth} flex-shrink-0 overflow-y-auto border-r border-slate-700 bg-slate-900 ${dataSidebarClassName}`}
        >
          {dataSidebar}
        </aside>
      )}

      {/* Main area */}
      <div className="flex-1 relative overflow-hidden">
        {/* Hamburger buttons (top-left overlay) */}
        <div className="absolute top-3 left-3 z-20 flex items-center gap-1.5">
          {/* Nav hamburger */}
          <button
            type="button"
            onClick={() => setNavOpen(!navOpen)}
            className={`flex items-center justify-center w-9 h-9 rounded-lg border transition-colors ${
              navOpen
                ? 'bg-blue-600 border-blue-500 text-white'
                : 'bg-slate-800/90 border-slate-600 text-slate-300 hover:bg-slate-700 hover:text-white'
            }`}
            title="Menu de navegacion"
          >
            <svg className="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
          </button>

          {/* Data sidebar hamburger (only if page has a data sidebar) */}
          {dataSidebar && (
            <button
              type="button"
              onClick={() => setDataOpen(!dataOpen)}
              className={`flex items-center justify-center w-9 h-9 rounded-lg border transition-colors ${
                dataOpen
                  ? 'bg-blue-600 border-blue-500 text-white'
                  : 'bg-slate-800/90 border-slate-600 text-slate-300 hover:bg-slate-700 hover:text-white'
              }`}
              title="Panel de datos"
            >
              <svg className="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
              </svg>
            </button>
          )}
        </div>

        {children}
      </div>
    </div>
  );
}
