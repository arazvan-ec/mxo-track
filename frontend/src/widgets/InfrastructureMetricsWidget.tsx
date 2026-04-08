import type { WidgetProps } from './types';
import { AnimatedCounter } from '@/components/ui/AnimatedCounter';

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

function ProgressBar({ value, max, warn }: { value: number; max: number; warn: boolean }) {
  const pct = Math.min((value / max) * 100, 100);
  return (
    <div className="mt-2 h-1.5 rounded-full overflow-hidden" style={{ backgroundColor: 'var(--color-border)' }}>
      <div
        className="h-full rounded-full transition-all duration-1000 ease-out"
        style={{
          width: `${pct}%`,
          backgroundColor: warn ? 'var(--color-warning)' : 'var(--color-accent)',
        }}
      />
    </div>
  );
}

export function InfrastructureMetricsWidget({ data }: WidgetProps) {
  const { live } = data as InfrastructureData;
  if (!live) return null;

  const isIngestionStale = live.last_ingestion.seconds_ago !== null && live.last_ingestion.seconds_ago > 1800;

  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3" style={{ gap: 'var(--section-gap, 0.75rem)' }}>
      {/* Positions table */}
      <div className="theme-card animate-fade-in-up" style={{ padding: 'var(--card-padding)' }}>
        <div className="flex items-center gap-2 mb-1">
          <svg className="h-4 w-4" style={{ color: live.positions.warning ? 'var(--color-warning)' : 'var(--color-accent)' }} fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5" />
          </svg>
          <p className="text-sm font-medium" style={{ color: 'var(--color-text-secondary)' }}>Posiciones</p>
        </div>
        <AnimatedCounter
          value={live.positions.row_count}
          className="text-xl font-bold tabular-nums"
          style={{
            color: live.positions.warning ? 'var(--color-warning)' : 'var(--color-text-primary)',
            fontFamily: 'var(--data-font)',
          }}
        />
        <ProgressBar value={live.positions.row_count} max={1_000_000} warn={live.positions.warning} />
        {live.positions.warning && (
          <p className="text-xs mt-1" style={{ color: 'var(--color-warning)' }}>Excede 1M — considerar purge</p>
        )}
      </div>

      {/* DB size */}
      <div className="theme-card animate-fade-in-up" style={{ padding: 'var(--card-padding)', animationDelay: '60ms' }}>
        <div className="flex items-center gap-2 mb-1">
          <svg className="h-4 w-4" style={{ color: 'var(--color-accent)' }} fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" d="M21.75 17.25v-.228a4.5 4.5 0 00-.12-1.03l-2.268-9.64a3.375 3.375 0 00-3.285-2.602H7.923a3.375 3.375 0 00-3.285 2.602l-2.268 9.64a4.5 4.5 0 00-.12 1.03v.228m19.5 0a3 3 0 01-3 3H5.25a3 3 0 01-3-3m19.5 0a3 3 0 00-3-3H5.25a3 3 0 00-3 3m16.5 0h.008v.008h-.008v-.008zm-3 0h.008v.008h-.008v-.008z" />
          </svg>
          <p className="text-sm font-medium" style={{ color: 'var(--color-text-secondary)' }}>Base de datos</p>
        </div>
        <AnimatedCounter
          value={live.disk.db_size_mb}
          formatter={(n) => `${n} MB`}
          className="text-xl font-bold tabular-nums"
          style={{ color: 'var(--color-text-primary)', fontFamily: 'var(--data-font)' }}
        />
        <ProgressBar value={live.disk.db_size_mb} max={5000} warn={live.disk.db_size_mb > 4000} />
      </div>

      {/* Last ingestion */}
      <div className="theme-card animate-fade-in-up" style={{ padding: 'var(--card-padding)', animationDelay: '120ms' }}>
        <div className="flex items-center gap-2 mb-1">
          <svg className="h-4 w-4" style={{ color: isIngestionStale ? 'var(--color-warning)' : 'var(--color-success)' }} fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <p className="text-sm font-medium" style={{ color: 'var(--color-text-secondary)' }}>Ultima ingestion</p>
        </div>
        {live.last_ingestion.timestamp !== null ? (
          <>
            <p className="text-xl font-bold" style={{ color: 'var(--color-text-primary)', fontFamily: 'var(--data-font)' }}>
              {formatSecondsAgo(live.last_ingestion.seconds_ago)}
            </p>
            <p className="text-xs mt-1" style={{ color: 'var(--color-text-muted)' }}>
              {new Date(live.last_ingestion.timestamp).toLocaleString('es-ES')}
            </p>
          </>
        ) : (
          <p className="text-sm" style={{ color: 'var(--color-text-muted)' }}>Sin datos</p>
        )}
      </div>
    </div>
  );
}
