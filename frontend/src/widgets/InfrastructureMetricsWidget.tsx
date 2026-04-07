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
      <div className="rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5 p-4">
        <div className="flex items-center gap-3">
          <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${live.positions.warning ? 'bg-amber-50' : 'bg-blue-50'}`}>
            <svg className={`h-5 w-5 ${live.positions.warning ? 'text-amber-600' : 'text-blue-600'}`} fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5" />
            </svg>
          </div>
          <div>
            <p className="text-sm font-medium text-gray-900">Posiciones (tabla)</p>
            <p className={`text-lg font-bold tabular-nums ${live.positions.warning ? 'text-amber-600' : 'text-gray-900'}`}>
              {Number(live.positions.row_count).toLocaleString('es-ES')}
            </p>
            {live.positions.warning && (
              <p className="text-xs text-amber-600">Excede 1M filas - considerar purge</p>
            )}
          </div>
        </div>
      </div>

      {/* DB size */}
      <div className="rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5 p-4">
        <div className="flex items-center gap-3">
          <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-50">
            <svg className="h-5 w-5 text-purple-600" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" d="M21.75 17.25v-.228a4.5 4.5 0 00-.12-1.03l-2.268-9.64a3.375 3.375 0 00-3.285-2.602H7.923a3.375 3.375 0 00-3.285 2.602l-2.268 9.64a4.5 4.5 0 00-.12 1.03v.228m19.5 0a3 3 0 01-3 3H5.25a3 3 0 01-3-3m19.5 0a3 3 0 00-3-3H5.25a3 3 0 00-3 3m16.5 0h.008v.008h-.008v-.008zm-3 0h.008v.008h-.008v-.008z" />
            </svg>
          </div>
          <div>
            <p className="text-sm font-medium text-gray-900">Base de datos</p>
            <p className="text-lg font-bold tabular-nums text-gray-900">{live.disk.db_size_mb} MB</p>
          </div>
        </div>
      </div>

      {/* Last ingestion */}
      <div className="rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5 p-4">
        <div className="flex items-center gap-3">
          <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${
            live.last_ingestion.seconds_ago !== null && live.last_ingestion.seconds_ago > 1800 ? 'bg-amber-50' : 'bg-teal-50'
          }`}>
            <svg className={`h-5 w-5 ${
              live.last_ingestion.seconds_ago !== null && live.last_ingestion.seconds_ago > 1800 ? 'text-amber-600' : 'text-teal-600'
            }`} fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <p className="text-sm font-medium text-gray-900">Última ingestion</p>
            {live.last_ingestion.timestamp !== null ? (
              <>
                <p className="text-sm font-bold tabular-nums text-gray-900">
                  {formatSecondsAgo(live.last_ingestion.seconds_ago)}
                </p>
                <p className="text-xs text-gray-400">
                  {new Date(live.last_ingestion.timestamp).toLocaleString('es-ES')}
                </p>
              </>
            ) : (
              <p className="text-sm text-gray-400">Sin datos</p>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
