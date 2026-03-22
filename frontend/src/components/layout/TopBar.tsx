import { useState, useEffect, useRef, useCallback } from 'react';
import { useMe } from '@/api/hooks/useMe';

interface TopBarProps {
  onMenuClick: () => void;
  /** Optional extra controls (e.g. data sidebar toggle) rendered after hamburger */
  extraControls?: React.ReactNode;
}

/* ── Search autocomplete ───────────────────────────────────────────── */

interface SearchResult {
  url: string;
  label: string;
  extra: string;
  type: 'shipment' | 'route' | 'vehicle';
}

function SearchBar() {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<SearchResult[]>([]);
  const [showDropdown, setShowDropdown] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);
  const timerRef = useRef<ReturnType<typeof setTimeout>>(undefined);

  const fetchResults = useCallback(async (q: string) => {
    if (q.length < 2) {
      setResults([]);
      setShowDropdown(false);
      return;
    }
    try {
      const resp = await fetch('/api/search?q=' + encodeURIComponent(q));
      const data = await resp.json();
      const r = data.results ?? [];
      setResults(r);
      setShowDropdown(r.length > 0);
    } catch {
      setResults([]);
      setShowDropdown(false);
    }
  }, []);

  const handleInput = useCallback((value: string) => {
    setQuery(value);
    clearTimeout(timerRef.current);
    timerRef.current = setTimeout(() => fetchResults(value), 300);
  }, [fetchResults]);

  // Close on outside click
  useEffect(() => {
    function handleClick(e: MouseEvent) {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setShowDropdown(false);
      }
    }
    document.addEventListener('mousedown', handleClick);
    return () => document.removeEventListener('mousedown', handleClick);
  }, []);

  const typeLabel = (type: string) =>
    type === 'shipment' ? 'Envio' : type === 'route' ? 'Ruta' : 'Vehiculo';

  const typeBadge = (type: string) => {
    if (type === 'shipment') return 'bg-blue-100 text-blue-600';
    if (type === 'route') return 'bg-amber-100 text-amber-600';
    return 'bg-green-100 text-green-600';
  };

  return (
    <div ref={containerRef} className="flex flex-1 items-center">
      <div className="relative w-full max-w-md">
        <form action="/search" method="GET" className="relative">
          <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
            <svg className="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
            </svg>
          </div>
          <input
            type="text"
            name="q"
            placeholder="Buscar envios, rutas, vehiculos..."
            className="block w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-9 pr-3 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-blue-500"
            value={query}
            onChange={(e) => handleInput(e.target.value)}
            onFocus={() => { if (results.length) setShowDropdown(true); }}
            autoComplete="off"
          />
        </form>

        {showDropdown && results.length > 0 && (
          <div className="absolute left-0 top-full z-50 mt-1 w-full rounded-lg bg-white shadow-lg ring-1 ring-gray-200 max-h-80 overflow-y-auto">
            {results.map((result) => (
              <a
                key={result.url}
                href={result.url}
                className="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 transition-colors border-b border-gray-100 last:border-0"
              >
                <span className={`inline-flex h-6 w-6 items-center justify-center rounded ${typeBadge(result.type)}`}>
                  {result.type === 'shipment' && (
                    <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                    </svg>
                  )}
                  {result.type === 'route' && (
                    <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z" />
                    </svg>
                  )}
                  {result.type === 'vehicle' && (
                    <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                    </svg>
                  )}
                </span>
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-medium text-gray-900 truncate">{result.label}</p>
                  <p className="text-xs text-gray-500 truncate">{result.extra}</p>
                </div>
                <span className="shrink-0 text-[10px] font-medium uppercase tracking-wider text-gray-400">
                  {typeLabel(result.type)}
                </span>
              </a>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

/* ── Language switcher ─────────────────────────────────────────────── */

function LanguageSwitcher() {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  const currentLocale = document.documentElement.lang || 'es';

  useEffect(() => {
    function handleClick(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) {
        setOpen(false);
      }
    }
    document.addEventListener('mousedown', handleClick);
    return () => document.removeEventListener('mousedown', handleClick);
  }, []);

  const switchLocale = (locale: string) => {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = `/locale/${locale}`;
    document.body.appendChild(form);
    form.submit();
  };

  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        className="flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-100 transition-colors"
      >
        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 21l5.25-11.25L21 21m-9-3h7.5M3 5.621a48.474 48.474 0 016-.371m0 0c1.12 0 2.233.038 3.334.114M9 5.25V3m3.334 2.364C11.176 10.658 7.69 15.08 3 17.502m9.334-12.138c.896.061 1.785.147 2.666.257m-4.589 8.495a18.023 18.023 0 01-3.827-5.802" />
        </svg>
        <span>{currentLocale.toUpperCase()}</span>
      </button>

      {open && (
        <div className="absolute right-0 z-10 mt-2 w-32 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-gray-900/5">
          <button
            type="button"
            onClick={() => switchLocale('es')}
            className={`block w-full px-4 py-2 text-left text-sm ${
              currentLocale === 'es' ? 'text-blue-600 font-medium bg-gray-50' : 'text-gray-700 hover:bg-gray-50'
            }`}
          >
            Espanol
          </button>
          <button
            type="button"
            onClick={() => switchLocale('en')}
            className={`block w-full px-4 py-2 text-left text-sm ${
              currentLocale === 'en' ? 'text-blue-600 font-medium bg-gray-50' : 'text-gray-700 hover:bg-gray-50'
            }`}
          >
            English
          </button>
        </div>
      )}
    </div>
  );
}

/* ── Notification bell ─────────────────────────────────────────────── */

function NotificationBell() {
  const [unreadCount, setUnreadCount] = useState(0);
  const eventSourceRef = useRef<EventSource | null>(null);
  const { data: me } = useMe();

  useEffect(() => {
    fetch('/api/notifications/unread-count', { credentials: 'same-origin' })
      .then((r) => (r.ok ? r.json() : null))
      .then((data) => {
        if (data?.unread_count != null) setUnreadCount(data.unread_count);
      })
      .catch(() => {});
  }, []);

  useEffect(() => {
    if (!me?.publicId) return;

    let es: EventSource | null = null;

    (async () => {
      try {
        const tokenRes = await fetch('/api/mercure-token', { credentials: 'include' });
        if (!tokenRes.ok) return;
        const { token } = await tokenRes.json();

        const meta = document.querySelector('meta[name="mercure-url"]');
        const mercureBase = meta?.getAttribute('content')
          || (window as unknown as Record<string, unknown>).__MERCURE_URL as string;
        if (!mercureBase) return;

        const hub = new URL(mercureBase);
        hub.searchParams.append('topic', `/map/users/${me.publicId}/notifications`);
        if (token) hub.searchParams.set('authorization', token);

        es = new EventSource(hub.toString());
        eventSourceRef.current = es;
        es.onmessage = (e) => {
          try {
            const data = JSON.parse(e.data);
            if (typeof data.unread_count === 'number') {
              setUnreadCount(data.unread_count);
            }
          } catch {
            // ignore parse errors
          }
        };
      } catch {
        // ignore connection errors
      }
    })();

    return () => {
      es?.close();
      eventSourceRef.current = null;
    };
  }, [me?.publicId]);

  return (
    <a
      href="/notifications"
      className="relative -m-2.5 p-2.5 text-gray-400 hover:text-gray-500 transition-colors"
    >
      <span className="sr-only">Ver notificaciones</span>
      <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
      </svg>
      {unreadCount > 0 && (
        <span className="absolute -top-1 -right-1 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">
          {unreadCount > 99 ? '99+' : unreadCount}
        </span>
      )}
    </a>
  );
}

/* ── User menu dropdown ────────────────────────────────────────────── */

function UserMenu() {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);
  const { data: me } = useMe();

  useEffect(() => {
    function handleClick(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) {
        setOpen(false);
      }
    }
    document.addEventListener('mousedown', handleClick);
    return () => document.removeEventListener('mousedown', handleClick);
  }, []);

  if (!me) return null;

  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        className="-m-1.5 flex items-center p-1.5"
      >
        <span className="sr-only">Menu de usuario</span>
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

      {open && (
        <>
          <div className="fixed inset-0 z-10" onClick={() => setOpen(false)} />
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
  );
}

/* ── TopBar (main export) ──────────────────────────────────────────── */

export function TopBar({ onMenuClick, extraControls }: TopBarProps) {
  return (
    <div className="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-x-4 border-b border-gray-200 bg-white px-4 shadow-sm sm:gap-x-6 sm:px-6 lg:px-8">
      {/* Hamburger */}
      <button
        type="button"
        onClick={onMenuClick}
        className="-m-2.5 p-2.5 text-gray-500 hover:text-gray-900 transition-colors"
      >
        <span className="sr-only">Abrir menu</span>
        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
        </svg>
      </button>

      {extraControls}

      <div className="flex flex-1 gap-x-4 self-stretch lg:gap-x-6">
        <SearchBar />
        <div className="flex items-center gap-x-4 lg:gap-x-6">
          <LanguageSwitcher />
          <NotificationBell />
          <div className="hidden lg:block lg:h-6 lg:w-px lg:bg-gray-200" aria-hidden="true" />
          <UserMenu />
        </div>
      </div>
    </div>
  );
}
