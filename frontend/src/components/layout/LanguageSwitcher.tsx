import { useState, useEffect, useRef } from 'react';
import { useMe } from '@/api/hooks/useMe';

const locales = [
  { code: 'es', label: 'ES' },
  { code: 'en', label: 'EN' },
] as const;

export function LanguageSwitcher() {
  const { data: me } = useMe();
  const [open, setOpen] = useState(false);
  const [csrfToken, setCsrfToken] = useState<string | null>(null);
  const wrapperRef = useRef<HTMLDivElement>(null);

  const currentLocale = me?.locale ?? 'es';

  useEffect(() => {
    fetch('/api/csrf-token/locale', { credentials: 'same-origin' })
      .then((r) => (r.ok ? r.json() : null))
      .then((data) => {
        if (data?.token) setCsrfToken(data.token);
      })
      .catch(() => {});
  }, []);

  useEffect(() => {
    function handleClickOutside(e: MouseEvent) {
      if (wrapperRef.current && !wrapperRef.current.contains(e.target as Node)) {
        setOpen(false);
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  async function switchLocale(locale: string) {
    if (!csrfToken) return;
    setOpen(false);

    try {
      await fetch(`/locale/${locale}`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `_token=${encodeURIComponent(csrfToken)}`,
        redirect: 'manual',
      });
      window.location.reload();
    } catch {
      window.location.reload();
    }
  }

  return (
    <div ref={wrapperRef} className="relative">
      <button
        type="button"
        onClick={() => setOpen(!open)}
        className="flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium transition-colors"
        style={{ color: 'var(--color-text-secondary)' }}
      >
        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            d="M10.5 21l5.25-11.25L21 21m-9-3h7.5M3 5.621a48.474 48.474 0 016-.371m0 0c1.12 0 2.233.038 3.334.114M9 5.25V3m3.334 2.364C11.176 10.658 7.69 15.08 3 17.502m9.334-12.138c.896.061 1.785.147 2.666.257m-4.589 8.495a18.023 18.023 0 01-3.827-5.802"
          />
        </svg>
        <span>{currentLocale.toUpperCase()}</span>
      </button>

      {open && (
        <div className="absolute right-0 z-10 mt-2 w-32 origin-top-right rounded-md py-1 shadow-lg theme-card">
          {locales.map((loc) => (
            <button
              key={loc.code}
              type="button"
              onClick={() => switchLocale(loc.code)}
              className="block w-full px-4 py-2 text-left text-sm transition-colors"
              style={{
                color: currentLocale === loc.code ? 'var(--color-accent)' : 'var(--color-text-secondary)',
                fontWeight: currentLocale === loc.code ? 500 : 400,
              }}
            >
              {loc.label === 'ES' ? 'Espanol' : 'English'}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
