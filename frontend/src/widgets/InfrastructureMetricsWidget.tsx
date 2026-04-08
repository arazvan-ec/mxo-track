import type { WidgetProps } from './types';

interface InfrastructureData {
  live?: {
    positions: { row_count: number; warning: boolean };
    disk: { db_size_mb: number };
    last_ingestion: { timestamp: string | null; seconds_ago: number | null };
  };
}

function formatSecondsAgo(seconds: number | null): string {
  if (seconds === null) return 'Sin datos';
  if (seconds < 60) return `hace ${seconds}s`;
  if (seconds < 3600) return `hace ${Math.floor(seconds / 60)}min`;
  if (seconds < 86400) return `hace ${Math.floor(seconds / 3600)}h`;
  return `hace ${Math.floor(seconds / 86400)}d`;
}

export function InfrastructureMetricsWidget({ data }: WidgetProps) {
  const { live } = data as InfrastructureData;
  if (!live) return null;

  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      {/* Positions table */}
      <div
        className="rounded-xl p-4 shadow-sm ring-1"
        style={{ backgroundColor: 'var(--color-surface-elevated)', borderColor: 'var(--color-border)' }}
      >
        <div className="flex items-center gap-3">
          <div
            className="flex h-10 w-10 items-center justify-center rounded-lg"
            style={{ backgroundColor: live.positions.warning ? 'rgba(245,158,11,0.1)' : 'rgba(59,130,246,0.1)' }}
          >
            <svg className="h-5 w-5" style={{ color: live.positions.warning ? 'var(--color-warning)' : '#3b82f6' }} fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5" />
            </svg>
          </div>
          <div>
            <p className="text-sm font-medium" style={{ color: 'var(--color-text-primary)' }}>Posiciones (tabla)</p>
            <p className="text-lg font-bold tabular-nums" style={{ color: live.positions.warning ? 'var(--color-warning)' : 'var(--color-text-primary)' }}>
              {Number(live.positions.row_count).toLocaleString('es-ES')}
            </p>
            {live.positions.warning && (
              <p className="text-xs" style={{ color: 'var(--color-warning)' }}>Excede 1M filas - considerar purge</p>
            )}
          </div>
        </div>
      </div>

      {/* DB size */}
      <div
        className="rounded-xl p-4 shadow-sm ring-1"
        style={{ backgroundColor: 'var(--color-surface-elevated)', borderColor: 'var(--color-border)' }}
      >
        <div className="flex items-center gap-3">
          <div className="flex h-10 w-10 items-center justify-center rounded-lg" style={{ backgroundColor: 'rgba(147,51,234,0.1)' }}>
            <svg className="h-5 w-5" style={{ color: '#9333ea' }} fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" d="M21.75 17.25v-.228a4.5 4.5 0 00-.12-1.03l-2.268-9.64a3.375 3.375 0 00-3.285-2.602H7.923a3.375 3.375 0 00-3.285 2.602l-2.268 9.64a4.5 4.5 0 00-.12 1.03v.228m19.5 0a3 3 0 01-3 3H5.25a3 3 0 01-3-3m19.5 0a3 3 0 00-3-3H5.25a3 3 0 00-3 3m16.5 0h.008v.008h-.008v-.008zm-3 0h.008v.008h-.008v-.008z" />
            </svg>
          </div>
          <div>
            <p className="text-sm font-medium" style={{ color: 'var(--color-text-primary)' }}>Base de datos</p>
            <p className="text-lg font-bold tabular-nums" style={{ color: 'var(--color-text-primary)' }}>{live.disk.db_size_mb} MB</p>
          </div>
        </div>
      </div>

      {/* Last ingestion */}
      <div
        className="rounded-xl p-4 shadow-sm ring-1"
        style={{ backgroundColor: 'var(--color-surface-elevated)', borderColor: 'var(--color-border)' }}
      >
        <div className="flex items-center gap-3">
          <div
            className="flex h-10 w-10 items-center justify-center rounded-lg"
            style={{ backgroundColor: live.last_ingestion.seconds_ago !== null && live.last_ingestion.seconds_ago > 1800 ? 'rgba(245,158,11,0.1)' : 'rgba(20,184,166,0.1)' }}
          >
            <svg className="h-5 w-5" style={{ color: live.last_ingestion.seconds_ago !== null && live.last_ingestion.seconds_ago > 1800 ? 'var(--color-warning)' : '#14b8a6' }} fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <p className="text-sm font-medium" style={{ color: 'var(--color-text-primary)' }}>Última ingestion</p>
            {live.last_ingestion.timestamp !== null ? (
              <>
                <p className="text-sm font-bold tabular-nums" style={{ color: 'var(--color-text-primary)' }}>
                  {formatSecondsAgo(live.last_ingestion.seconds_ago)}
                </p>
                <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                  {new Date(live.last_ingestion.timestamp).toLocaleString('es-ES')}
                </p>
              </>
            ) : (
              <p className="text-sm" style={{ color: 'var(--color-text-muted)' }}>Sin datos</p>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
