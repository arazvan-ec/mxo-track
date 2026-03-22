import { useState, type ReactNode } from 'react';
import { NavigationSidebar } from './NavigationSidebar';
import { TopBar } from './TopBar';

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
 * Shell that replicates the Twig base layout:
 *
 * 1. Unified top bar (hamburger + search + lang + notifications + user) via TopBar
 * 2. Below: optional inline data sidebar + main content
 * 3. Navigation sidebar opens as overlay (same as Twig pages)
 */
export function DualMenuShell({
  dataSidebar,
  dataSidebarWidth = 'w-80',
  dataSidebarClassName = '',
  children,
}: DualMenuShellProps) {
  const [navOpen, setNavOpen] = useState(false);
  const [dataOpen, setDataOpen] = useState(true);

  const dataSidebarToggle = dataSidebar ? (
    <button
      type="button"
      onClick={() => setDataOpen((o) => !o)}
      className={`-m-1.5 p-1.5 rounded-md transition-colors ${
        dataOpen
          ? 'text-blue-600 bg-blue-50 hover:bg-blue-100'
          : 'text-gray-400 hover:text-gray-600 hover:bg-gray-100'
      }`}
      title={dataOpen ? 'Ocultar panel de datos' : 'Mostrar panel de datos'}
    >
      <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
      </svg>
    </button>
  ) : undefined;

  return (
    <div className="flex flex-col h-screen w-full">
      {/* Navigation sidebar — overlay (same as Twig pages) */}
      {navOpen && (
        <NavigationSidebar
          mode="overlay"
          onClose={() => setNavOpen(false)}
        />
      )}

      {/* ── Unified top bar ──────────────────────────────────────────── */}
      <TopBar
        onMenuClick={() => setNavOpen(true)}
        extraControls={dataSidebarToggle}
      />

      {/* ── Content area (sidebar + main) ──────────────────────────── */}
      <div className="flex flex-1 overflow-hidden">
        {/* Data sidebar (collapsible, inline) */}
        {dataSidebar && dataOpen && (
          <aside
            className={`${dataSidebarWidth} flex-shrink-0 flex flex-col overflow-hidden border-r border-slate-700 bg-slate-900 ${dataSidebarClassName}`}
          >
            <div className="flex-1 overflow-y-auto">
              {dataSidebar}
            </div>
          </aside>
        )}

        {/* Main area */}
        <div className="flex-1 relative overflow-hidden">
          {children}
        </div>
      </div>
    </div>
  );
}
