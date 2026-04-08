import type { StopSelectionData } from '@/hooks/useMapSelection';
import { STOP_STATUS_COLORS } from '@/components/maps/shared/colors';

interface Props {
  stop: StopSelectionData;
  userRole?: string;
  onClose: () => void;
}

export function StopActionPanel({ stop, userRole, onClose }: Props) {
  const statusColor = STOP_STATUS_COLORS[stop.status] ?? STOP_STATUS_COLORS.PENDING;
  const isAdmin = userRole === 'ROLE_ADMIN' || userRole === 'ROLE_OPERATOR';
  const isDriver = userRole === 'ROLE_DRIVER';

  const handleCopyAddress = () => {
    navigator.clipboard.writeText(stop.address);
  };

  return (
    <div className="rounded-lg border overflow-hidden" style={{ borderColor: 'var(--color-border-subtle)', backgroundColor: 'var(--color-surface-elevated)' }}>
      {/* Header */}
      <div className="flex items-center justify-between px-3 py-2 border-b" style={{ borderColor: 'var(--color-border-subtle)' }}>
        <div className="flex items-center gap-2">
          <span
            className="flex-shrink-0 w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold text-white"
            style={{ backgroundColor: statusColor }}
          >
            {stop.sequence}
          </span>
          <span
            className="text-[10px] font-medium uppercase px-1.5 py-0.5 rounded"
            style={{ color: statusColor, backgroundColor: `${statusColor}20` }}
          >
            {stop.status}
          </span>
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
      <div className="px-3 py-2 space-y-1.5">
        <div className="text-sm" style={{ color: 'var(--color-text-primary)' }}>{stop.address}</div>
        {stop.recipientName && (
          <div className="text-xs" style={{ color: 'var(--color-text-secondary)' }}>
            {stop.recipientName}
            {stop.recipientPhone && <span className="ml-2 font-mono">{stop.recipientPhone}</span>}
          </div>
        )}
        {stop.etaTime && stop.status === 'PENDING' && (
          <div className="text-[11px]" style={{ color: 'var(--color-text-muted)' }}>ETA: {stop.etaTime}</div>
        )}
        {stop.deliveredAt && (
          <div className="text-[11px]" style={{ color: 'var(--color-success)' }}>Entregado: {stop.deliveredAt}</div>
        )}
        {stop.exceptionCode && (
          <div className="text-[11px] text-orange-400">Excepcion: {stop.exceptionCode}</div>
        )}
      </div>

      {/* Actions */}
      <div className="flex flex-wrap gap-1.5 px-3 py-2 border-t" style={{ borderColor: 'var(--color-border-subtle)' }}>
        {stop.shipmentPublicId && (isAdmin || userRole === 'ROLE_CUSTOMER') && (
          <a
            href={`/admin/shipments/${stop.shipmentPublicId}`}
            className="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-medium bg-blue-500/20 text-blue-400 hover:bg-blue-500/30 transition-colors"
          >
            <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
            Ver envio
          </a>
        )}

        <button
          onClick={handleCopyAddress}
          className="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-medium transition-colors"
          style={{ backgroundColor: 'var(--color-accent-muted)', color: 'var(--color-text-secondary)' }}
          type="button"
        >
          <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
          </svg>
          Copiar direccion
        </button>

        {stop.recipientPhone && isDriver && (
          <a
            href={`tel:${stop.recipientPhone}`}
            className="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-medium bg-emerald-500/20 text-emerald-400 hover:bg-emerald-500/30 transition-colors"
          >
            <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
            </svg>
            Llamar
          </a>
        )}

        {stop.routePublicId && isAdmin && (
          <a
            href={`/app/admin/routes/${stop.routePublicId}`}
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
