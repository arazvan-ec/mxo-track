import { useEffect, useRef } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '../client';

interface MercureTokenResponse {
  ok: boolean;
  token: string;
}

export function useMercure<T>(
  topics: string[],
  onMessage: (data: T) => void,
  enabled = true,
) {
  const onMessageRef = useRef(onMessage);
  onMessageRef.current = onMessage;

  const tokenQuery = useQuery({
    queryKey: ['mercure-token'],
    queryFn: () => api.get<MercureTokenResponse>('/api/mercure-token'),
    staleTime: 5 * 60 * 1000,
    enabled,
  });

  useEffect(() => {
    if (!tokenQuery.data?.token || topics.length === 0 || !enabled) return;

    const mercureUrl = (window as unknown as Record<string, unknown>).__MERCURE_URL as string;
    if (!mercureUrl) return;

    const url = new URL(mercureUrl);
    topics.forEach((t) => url.searchParams.append('topic', t));

    const es = new EventSource(url.toString(), {
      withCredentials: false,
    });

    // Authorization via Last-Event-ID workaround or query param
    // Mercure 0.14+ supports authorization via cookie or URL
    // For now, append token as query parameter
    url.searchParams.set('authorization', tokenQuery.data.token);

    const esWithAuth = new EventSource(url.toString());

    esWithAuth.onmessage = (event) => {
      try {
        const data = JSON.parse(event.data) as T;
        onMessageRef.current(data);
      } catch {
        // Ignore malformed messages
      }
    };

    es.close(); // Close the first one without auth

    return () => {
      esWithAuth.close();
    };
  }, [tokenQuery.data?.token, topics.join(','), enabled]);
}
