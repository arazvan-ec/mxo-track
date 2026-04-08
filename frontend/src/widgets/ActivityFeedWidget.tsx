import { useState, useEffect, useRef, useCallback } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '@/api/client';
import type { WidgetProps } from './types';

interface FeedEvent {
  id: string;
  vehicleName: string;
  coords: string;
  speed: number | null;
  time: string;
}

interface MercureTokenResponse {
  token: string;
}

interface Vehicle {
  public_id: string;
  name: string;
}

interface VehiclesResponse {
  items: Vehicle[];
}

interface ActivityFeedData {
  mercurePublicUrl?: string;
}

export function ActivityFeedWidget({ data }: WidgetProps) {
  const { mercurePublicUrl } = data as ActivityFeedData;
  const [feed, setFeed] = useState<FeedEvent[]>([]);
  const [connected, setConnected] = useState(false);
  const eventSourceRef = useRef<EventSource | null>(null);
  const vehicleNamesRef = useRef<Record<string, string>>({});

  // Fetch vehicles for name resolution
  const { data: vehiclesData } = useQuery({
    queryKey: ['vehicles-for-feed'],
    queryFn: () => api.get<VehiclesResponse>('/api/vehicles'),
    staleTime: 5 * 60 * 1000,
  });

  // Fetch Mercure token
  const { data: tokenData } = useQuery({
    queryKey: ['mercure-token-feed'],
    queryFn: () => api.get<MercureTokenResponse>('/api/mercure-token'),
    staleTime: 5 * 60 * 1000,
  });

  // Update vehicle names map
  useEffect(() => {
    if (vehiclesData?.items) {
      const map: Record<string, string> = {};
      vehiclesData.items.forEach((v) => { map[v.public_id] = v.name; });
      vehicleNamesRef.current = map;
    }
  }, [vehiclesData]);

  const addEvent = useCallback((event: FeedEvent) => {
    setFeed((prev) => {
      const next = [event, ...prev];
      return next.length > 50 ? next.slice(0, 50) : next;
    });
  }, []);

  // Connect to Mercure SSE
  useEffect(() => {
    if (!mercurePublicUrl || !vehiclesData?.items?.length || !tokenData?.token) return;

    const hub = new URL(mercurePublicUrl);
    vehiclesData.items.forEach((v) => {
      hub.searchParams.append('topic', `/map/vehicles/${v.public_id}/position`);
    });
    hub.searchParams.set('authorization', tokenData.token);

    const es = new EventSource(hub.toString());
    eventSourceRef.current = es;

    es.onopen = () => setConnected(true);
    es.onerror = () => setConnected(false);

    es.onmessage = (event) => {
      try {
        const d = JSON.parse(event.data);
        const vehicleName = vehicleNamesRef.current[d.vehicleId] || d.vehicleId || 'Vehículo';
        const now = new Date();
        addEvent({
          id: `${Date.now()}-${Math.random()}`,
          vehicleName,
          coords: `${d.lat?.toFixed(4) ?? '?'}, ${d.lng?.toFixed(4) ?? '?'}`,
          speed: d.speed ? Math.round(d.speed) : null,
          time: now.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
        });
      } catch {
        // ignore parse errors
      }
    };

    return () => {
      es.close();
      eventSourceRef.current = null;
      setConnected(false);
    };
  }, [mercurePublicUrl, vehiclesData, tokenData, addEvent]);

  return (
    <div>
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-2">
          <span className="relative flex h-2 w-2">
            <span className={`absolute inline-flex h-full w-full animate-ping rounded-full opacity-75 ${connected ? 'bg-emerald-400' : 'bg-red-400'}`} />
            <span className={`relative inline-flex h-2 w-2 rounded-full ${connected ? 'bg-emerald-500' : 'bg-red-500'}`} />
          </span>
          <span className="text-xs font-medium" style={{ color: 'var(--color-text-secondary)' }}>{connected ? 'En vivo' : 'Desconectado'}</span>
        </div>
      </div>

      <div
        className="rounded-xl shadow-sm ring-1 overflow-hidden"
        style={{ backgroundColor: 'var(--color-surface-elevated)', borderColor: 'var(--color-border)' }}
      >
        <div className="max-h-80 overflow-y-auto">
          {feed.length === 0 ? (
            <div className="px-6 py-8 text-center">
              <svg className="mx-auto h-10 w-10" style={{ color: 'var(--color-text-muted)' }} fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M3 3l8.735 8.735m0 0a.374.374 0 11.53.53m-.53-.53l.53.53m0 0L21 21M14.652 9.348a3.75 3.75 0 010 5.304m2.121-7.425a6.75 6.75 0 010 9.546m2.121-11.667c3.808 3.807 3.808 9.98 0 13.788" />
              </svg>
              <p className="mt-2 text-sm" style={{ color: 'var(--color-text-secondary)' }}>Esperando actividad en tiempo real...</p>
              <p className="mt-1 text-xs" style={{ color: 'var(--color-text-muted)' }}>Las posiciones de vehículos aparecerán aquí</p>
            </div>
          ) : (
            feed.map((event) => (
              <div
                key={event.id}
                className="flex items-center gap-4 px-6 py-3 transition-colors hover:opacity-80 border-b last:border-b-0"
                style={{ borderColor: 'var(--color-border)' }}
              >
                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-cyan-100">
                  <svg className="h-4 w-4 text-cyan-600" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                  </svg>
                </div>
                <div className="min-w-0 flex-1">
                  <p className="text-sm" style={{ color: 'var(--color-text-primary)' }}>
                    <span className="font-medium">{event.vehicleName}</span>
                    <span style={{ color: 'var(--color-text-secondary)' }}> reportó posición</span>
                  </p>
                  <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                    {event.coords}{event.speed ? ` · ${event.speed} km/h` : ''}
                  </p>
                </div>
                <span className="text-xs shrink-0" style={{ color: 'var(--color-text-muted)' }}>{event.time}</span>
              </div>
            ))
          )}
        </div>
      </div>
    </div>
  );
}
