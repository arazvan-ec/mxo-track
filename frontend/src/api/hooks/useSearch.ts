import { useState, useRef, useCallback } from 'react';

interface SearchResult {
  type: 'shipment' | 'route' | 'vehicle';
  label: string;
  extra: string;
  url: string;
}

export function useSearch() {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<SearchResult[]>([]);
  const [isOpen, setIsOpen] = useState(false);
  const timerRef = useRef<ReturnType<typeof setTimeout>>();

  const search = useCallback((value: string) => {
    setQuery(value);

    if (timerRef.current) clearTimeout(timerRef.current);

    if (value.length < 2) {
      setResults([]);
      setIsOpen(false);
      return;
    }

    timerRef.current = setTimeout(async () => {
      try {
        const resp = await fetch(`/api/search?q=${encodeURIComponent(value)}`, {
          credentials: 'same-origin',
        });
        if (!resp.ok) return;
        const data = await resp.json();
        const items: SearchResult[] = data.results ?? [];
        setResults(items);
        setIsOpen(items.length > 0);
      } catch {
        setResults([]);
        setIsOpen(false);
      }
    }, 300);
  }, []);

  const close = useCallback(() => {
    setIsOpen(false);
  }, []);

  return { query, search, results, isOpen, close };
}
