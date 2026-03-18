import { useEffect, useMemo, useRef } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '../client';

interface MercureTokenResponse {
  ok: boolean;
  token: string;
}

/**
 * Mercure URL is fetched from /api/me or injected as a meta tag.
 * For now, try multiple sources.
 */
function getMercureUrl(): string | null {
  // Check meta tag first
  const meta = document.querySelector('meta[name="mercure-url"]');
  if (meta) return meta.getAttribute('content');

  // Check global (set by Twig base template or env)
  const global = (window as unknown as Record<string, unknown>).__MERCURE_URL;
  if (typeof global === 'string' && global) return global;

  // Fallback: relative to current origin
  return null;
}

export function useMercure<T>(
  topics: string[],
  onMessage: (data: T) => void,
  enabled = true,
) {
  const onMessageRef = useRef(onMessage);
  onMessageRef.current = onMessage;

  // Stabilize topics to avoid re-creating EventSource on every render
  const topicsKey = useMemo(() => topics.sort().join('\n'), [topics]);

  const tokenQuery = useQuery({
    queryKey: ['mercure-token'],
    queryFn: () => api.get<MercureTokenResponse>('/api/mercure-token'),
    staleTime: 5 * 60 * 1000,
    enabled,
  });

  useEffect(() => {
    if (!tokenQuery.data?.token || topics.length === 0 || !enabled) return;

    const mercureUrl = getMercureUrl();
    if (!mercureUrl) return;

    const url = new URL(mercureUrl);
    topics.forEach((t) => url.searchParams.append('topic', t));
    url.searchParams.set('authorization', tokenQuery.data.token);

    const es = new EventSource(url.toString());

    es.onmessage = (event) => {
      try {
        const data = JSON.parse(event.data) as T;
        onMessageRef.current(data);
      } catch {
        // Ignore malformed messages
      }
    };

    return () => {
      es.close();
    };
  }, [tokenQuery.data?.token, topicsKey, enabled]);
}
