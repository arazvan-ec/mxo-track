import type { WidgetProps } from './types';

interface ServiceStatus {
  ok: boolean;
  latency_ms: number;
}

interface SystemHealthData {
  health?: {
    db_ok: boolean;
    redis_ok: boolean;
    traccar_ok: boolean;
    mercure_ok: boolean;
    osrm_ok: boolean;
    vroom_ok: boolean;
  };
  live?: {
    database: ServiceStatus;
    redis: ServiceStatus;
    traccar: ServiceStatus;
    mercure: ServiceStatus;
    osrm: ServiceStatus;
    vroom: ServiceStatus;
  };
}

interface ServiceCardProps {
  name: string;
  ok: boolean;
  latencyMs: number;
  latencyThreshold: number;
  icon: React.ReactNode;
}

function ServiceCard({ name, ok, latencyMs, latencyThreshold, icon }: ServiceCardProps) {
  return (
    <div
      className="rounded-xl p-4 shadow-sm ring-1"
      style={{ backgroundColor: 'var(--color-surface-elevated)', borderColor: 'var(--color-border)' }}
    >
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div
            className="flex h-10 w-10 items-center justify-center rounded-lg"
            style={{ backgroundColor: ok ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)' }}
          >
            <span style={{ color: ok ? 'var(--color-success)' : 'var(--color-error)' }}>{icon}</span>
          </div>
          <div>
            <p className="text-sm font-medium" style={{ color: 'var(--color-text-primary)' }}>{name}</p>
            <p className="text-xs" style={{ color: ok ? 'var(--color-success)' : 'var(--color-error)' }}>
              {ok ? 'Conectado' : 'Sin conexión'}
            </p>
          </div>
        </div>
        <span
          className="text-xs font-mono tabular-nums"
          style={{ color: latencyMs > latencyThreshold ? 'var(--color-warning)' : 'var(--color-text-muted)' }}
        >
          {latencyMs}ms
        </span>
      </div>
    </div>
  );
}

// Simple SVG icons for each service
const DatabaseIcon = () => (
  <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
    <path strokeLinecap="round" strokeLinejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75" />
  </svg>
);

const ServerIcon = () => (
  <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
    <path strokeLinecap="round" strokeLinejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7m0 0a3 3 0 01-3 3m0 3h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008zm-3 6h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008z" />
  </svg>
);

const WifiIcon = () => (
  <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
    <path strokeLinecap="round" strokeLinejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z" />
  </svg>
);

const BoltIcon = () => (
  <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
    <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
  </svg>
);

const MapIcon = () => (
  <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
    <path strokeLinecap="round" strokeLinejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z" />
  </svg>
);

const TruckIcon = () => (
  <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
    <path strokeLinecap="round" strokeLinejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
  </svg>
);

export function SystemHealthWidget({ data }: WidgetProps) {
  const { health, live } = data as SystemHealthData;
  if (!health || !live) return null;

  const services = [
    { name: 'PostgreSQL', ok: health.db_ok, latencyMs: live.database.latency_ms, threshold: 100, icon: <DatabaseIcon /> },
    { name: 'Redis', ok: health.redis_ok, latencyMs: live.redis.latency_ms, threshold: 50, icon: <ServerIcon /> },
    { name: 'Traccar API', ok: health.traccar_ok, latencyMs: live.traccar.latency_ms, threshold: 500, icon: <WifiIcon /> },
    { name: 'Mercure Hub', ok: health.mercure_ok, latencyMs: live.mercure.latency_ms, threshold: 500, icon: <BoltIcon /> },
    { name: 'OSRM (Routing)', ok: health.osrm_ok, latencyMs: live.osrm.latency_ms, threshold: 500, icon: <MapIcon /> },
    { name: 'VROOM (Optimizer)', ok: health.vroom_ok, latencyMs: live.vroom.latency_ms, threshold: 500, icon: <TruckIcon /> },
  ];

  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      {services.map((s) => (
        <ServiceCard key={s.name} name={s.name} ok={s.ok} latencyMs={s.latencyMs} latencyThreshold={s.threshold} icon={s.icon} />
      ))}
    </div>
  );
}
