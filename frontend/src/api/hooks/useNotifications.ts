import { useState, useEffect, useRef } from 'react';
import { useMe } from './useMe';

export function useNotifications() {
  const [unreadCount, setUnreadCount] = useState(0);
  const eventSourceRef = useRef<EventSource | null>(null);
  const { data: me } = useMe();

  // Fetch initial count
  useEffect(() => {
    fetch('/api/notifications/unread-count', { credentials: 'same-origin' })
      .then((r) => (r.ok ? r.json() : null))
      .then((data) => {
        if (data && typeof data.unread_count === 'number') {
          setUnreadCount(data.unread_count);
        }
      })
      .catch(() => {});
  }, []);

  // Subscribe to Mercure SSE for live updates
  useEffect(() => {
    if (!me?.publicId) return;

    let es: EventSource | null = null;

    (async () => {
      try {
        const tokenRes = await fetch('/api/mercure-token', { credentials: 'same-origin' });
        if (!tokenRes.ok) return;
        const { token } = await tokenRes.json();

        const mercureUrl = document.querySelector<HTMLElement>('[data-mercure-url]')?.dataset.mercureUrl;
        if (!mercureUrl) return;

        const hub = new URL(mercureUrl);
        hub.searchParams.append('topic', `/map/users/${me.publicId}/notifications`);
        if (token) hub.searchParams.set('authorization', token);

        es = new EventSource(hub);
        eventSourceRef.current = es;

        es.onmessage = (e) => {
          try {
            const data = JSON.parse(e.data);
            if (typeof data.unread_count === 'number') {
              setUnreadCount(data.unread_count);
            }
          } catch {}
        };
      } catch {}
    })();

    return () => {
      es?.close();
      eventSourceRef.current = null;
    };
  }, [me?.publicId]);

  return { unreadCount };
}
