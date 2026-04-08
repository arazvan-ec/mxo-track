import type { VehicleSelectionData } from '@/hooks/useMapSelection';

interface Props {
  vehicle: VehicleSelectionData;
  userRole?: string;
  onClose: () => void;
}

export function VehicleActionPanel({ vehicle, userRole, onClose }: Props) {
  const isAdmin = userRole === 'ROLE_ADMIN' || userRole === 'ROLE_OPERATOR';

  return (
    <div className="rounded-lg border overflow-hidden" style={{ borderColor: 'var(--color-border-subtle)', backgroundColor: 'var(--color-surface-elevated)' }}>
      {/* Header */}
      <div className="flex items-center justify-between px-3 py-2 border-b" style={{ borderColor: 'var(--color-border-subtle)' }}>
        <div className="flex items-center gap-2">
          <span className="flex-shrink-0 w-6 h-6 rounded-full bg-blue-600 flex items-center justify-center">
            <svg className="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 24 24">
              <path d="M18 18.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3zm-12 0a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM19.92 6.09C19.72 5.46 19.16 5 18.5 5h-11c-.66 0-1.21.42-1.42 1.02L4 12v6c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h10v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-6l-2.08-5.91zM7.5 15a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm9 0a1.5 1.5 0 110-3 1.5 1.5 0 010 3zM5.81 10l1.04-3h10.29l1.04 3H5.81z" />
            </svg>
          </span>
          <span className="text-sm font-medium" style={{ color: 'var(--color-text-primary)' }}>{vehicle.name}</span>
        </div>
        <button
          onClick={onClose}
          className="transition-colors p-1"
          style={{ color: 'var(--color-text-muted)' }}
          type="button"
        >
          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      {/* Details */}
      <div className="px-3 py-2 space-y-1">
        {vehicle.driverName && (
          <div className="flex items-center gap-2 text-xs">
            <span style={{ color: 'var(--color-text-muted)' }}>Conductor:</span>
            <span style={{ color: 'var(--color-text-primary)' }}>{vehicle.driverName}</span>
          </div>
        )}
        {vehicle.speed != null && (
          <div className="flex items-center gap-2 text-xs">
            <span style={{ color: 'var(--color-text-muted)' }}>Velocidad:</span>
            <span style={{ color: 'var(--color-text-primary)' }}>{Math.round(vehicle.speed)} km/h</span>
          </div>
        )}
        {vehicle.routeName && (
          <div className="flex items-center gap-2 text-xs">
            <span style={{ color: 'var(--color-text-muted)' }}>Ruta:</span>
            <span style={{ color: 'var(--color-text-primary)' }}>{vehicle.routeName}</span>
          </div>
        )}
      </div>

      {/* Actions */}
      <div className="flex flex-wrap gap-1.5 px-3 py-2 border-t" style={{ borderColor: 'var(--color-border-subtle)' }}>
        {vehicle.routePublicId && (isAdmin || userRole === 'ROLE_CUSTOMER') && (
          <a
            href={`/app/admin/routes/${vehicle.routePublicId}`}
            className="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-medium bg-violet-500/20 text-violet-400 hover:bg-violet-500/30 transition-colors"
          >
            <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
            </svg>
            Ver ruta
          </a>
        )}
      </div>
    </div>
  );
}
