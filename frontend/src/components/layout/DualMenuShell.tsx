import { useState, type ReactNode } from 'react';
import { NavigationSidebar } from './NavigationSidebar';
import { useMe } from '@/api/hooks/useMe';

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
 * 1. White top bar (hamburger + page title area + user avatar)
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
  const [userMenuOpen, setUserMenuOpen] = useState(false);
  const { data: me } = useMe();

  return (
    <div className="flex flex-col h-screen w-full">
      {/* Navigation sidebar — overlay (same as Twig pages) */}
      {navOpen && (
        <NavigationSidebar
          mode="overlay"
          onClose={() => setNavOpen(false)}
        />
      )}

      {/* ── Top bar (mirrors Twig base.html.twig) ──────────────────── */}
      <div className="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-x-4 border-b border-gray-200 bg-white px-4 shadow-sm sm:gap-x-6 sm:px-6 lg:px-8">
        {/* Hamburger */}
        <button
          type="button"
          onClick={() => setNavOpen(true)}
          className="-m-2.5 p-2.5 text-gray-500 hover:text-gray-900 transition-colors"
        >
          <span className="sr-only">Abrir menu</span>
          <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
          </svg>
        </button>

        <div className="flex flex-1 gap-x-4 self-stretch items-center lg:gap-x-6">
          {/* Data sidebar toggle */}
          {dataSidebar && (
            <button
              type="button"
              onClick={() => setDataOpen((o) => !o)}
              className={`-m-1.5 p-1.5 rounded-md transition-colors ${
                dataOpen
                  ? 'text-brand-600 bg-brand-50 hover:bg-brand-100'
                  : 'text-gray-400 hover:text-gray-600 hover:bg-gray-100'
              }`}
              title={dataOpen ? 'Ocultar panel de datos' : 'Mostrar panel de datos'}
            >
              <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
              </svg>
            </button>
          )}

          {/* Spacer */}
          <div className="flex-1" />

          {/* User avatar + dropdown */}
          {me && (
            <div className="relative">
              <button
                type="button"
                onClick={() => setUserMenuOpen((o) => !o)}
                className="-m-1.5 flex items-center p-1.5"
              >
                <span className="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white">
                  {(me.email ?? '?')[0].toUpperCase()}
                </span>
                <span className="hidden lg:flex lg:items-center">
                  <span className="ml-3 text-sm font-semibold leading-6 text-gray-900">
                    {me.email}
                  </span>
                  <svg className="ml-2 h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fillRule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clipRule="evenodd" />
                  </svg>
                </span>
              </button>

              {userMenuOpen && (
                <>
                  <div className="fixed inset-0 z-10" onClick={() => setUserMenuOpen(false)} />
                  <div className="absolute right-0 z-20 mt-2.5 w-48 origin-top-right rounded-md bg-white py-2 shadow-lg ring-1 ring-gray-900/5">
                    <div className="px-4 py-2 border-b border-gray-100">
                      <p className="text-xs text-gray-500">Conectado como</p>
                      <p className="text-sm font-medium text-gray-900 truncate">{me.email}</p>
                    </div>
                    <a
                      href="/logout"
                      className="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors"
                    >
                      Cerrar sesion
                    </a>
                  </div>
                </>
              )}
            </div>
          )}
        </div>
      </div>

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
