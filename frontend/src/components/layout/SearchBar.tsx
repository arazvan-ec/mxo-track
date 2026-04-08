import { useState, useRef, useEffect } from 'react';
import { useSearch } from '@/api/hooks/useSearch';

interface SearchBarProps {
  compact?: boolean;
}

const typeIcons: Record<string, { bg: string; color: string; path: string }> = {
  shipment: {
    bg: 'bg-blue-100',
    color: 'text-blue-600',
    path: 'M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9',
  },
  route: {
    bg: 'bg-amber-100',
    color: 'text-amber-600',
    path: 'M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z',
  },
  vehicle: {
    bg: 'bg-green-100',
    color: 'text-green-600',
    path: 'M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12',
  },
};

const typeLabels: Record<string, string> = {
  shipment: 'Envio',
  route: 'Ruta',
  vehicle: 'Vehiculo',
};

export function SearchBar({ compact = false }: SearchBarProps) {
  const { query, search, results, isOpen, close } = useSearch();
  const [expanded, setExpanded] = useState(false);
  const wrapperRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    function handleClickOutside(e: MouseEvent) {
      if (wrapperRef.current && !wrapperRef.current.contains(e.target as Node)) {
        close();
        if (compact) setExpanded(false);
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [close, compact]);

  useEffect(() => {
    if (expanded && inputRef.current) {
      inputRef.current.focus();
    }
  }, [expanded]);

  if (compact && !expanded) {
    return (
      <button
        type="button"
        onClick={() => setExpanded(true)}
        className="p-2 transition-colors"
        style={{ color: 'var(--color-text-muted)' }}
        title="Buscar"
      >
        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
        </svg>
      </button>
    );
  }

  return (
    <div ref={wrapperRef} className="relative w-full max-w-md">
      <form action="/search" method="GET" className="relative">
        <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
          <svg className="h-4 w-4" style={{ color: 'var(--color-text-muted)' }} fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
          </svg>
        </div>
        <input
          ref={inputRef}
          type="text"
          name="q"
          placeholder="Buscar envios, rutas, vehiculos..."
          className="block w-full rounded-lg border py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"
          style={{
            backgroundColor: 'var(--color-surface-elevated)',
            borderColor: 'var(--color-border)',
            color: 'var(--color-text-primary)',
          }}
          value={query}
          onChange={(e) => search(e.target.value)}
          autoComplete="off"
        />
      </form>

      {isOpen && results.length > 0 && (
        <div className="absolute left-0 top-full z-50 mt-1 w-full rounded-lg shadow-lg max-h-80 overflow-y-auto theme-card">
          {results.map((result) => {
            const icon = typeIcons[result.type];
            return (
              <a
                key={result.url}
                href={result.url}
                className="flex items-center gap-3 px-4 py-2.5 transition-colors border-b last:border-0"
                style={{ borderColor: 'var(--color-border)' }}
              >
                {icon && (
                  <span className={`inline-flex h-6 w-6 items-center justify-center rounded ${icon.bg} ${icon.color}`}>
                    <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" d={icon.path} />
                    </svg>
                  </span>
                )}
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-medium truncate" style={{ color: 'var(--color-text-primary)' }}>{result.label}</p>
                  <p className="text-xs truncate" style={{ color: 'var(--color-text-muted)' }}>{result.extra}</p>
                </div>
                <span className="shrink-0 text-[10px] font-medium uppercase tracking-wider" style={{ color: 'var(--color-text-muted)' }}>
                  {typeLabels[result.type] ?? result.type}
                </span>
              </a>
            );
          })}
        </div>
      )}
    </div>
  );
}
