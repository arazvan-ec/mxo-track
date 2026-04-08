import { STOP_STATUS_COLORS } from './colors';

interface Props {
  sequence: number;
  address: string;
  status: string;
  recipientName?: string;
  shipmentPublicId?: string;
}

export function StopPopup({ sequence, address, status, recipientName, shipmentPublicId }: Props) {
  const statusColor = STOP_STATUS_COLORS[status] ?? STOP_STATUS_COLORS.PENDING;

  return (
    <div className="min-w-[180px] max-w-[240px] text-xs leading-relaxed">
      <div className="flex items-center gap-2 mb-1">
        <span
          className="flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold text-white"
          style={{ backgroundColor: statusColor }}
        >
          {sequence}
        </span>
        <span
          className="text-[10px] font-medium uppercase px-1.5 py-0.5 rounded"
          style={{ color: statusColor, backgroundColor: `${statusColor}20` }}
        >
          {status}
        </span>
      </div>
      <div className="text-[11px] mb-0.5 line-clamp-2" style={{ color: 'var(--color-text-primary)' }}>{address}</div>
      {recipientName && (
        <div className="text-[10px]" style={{ color: 'var(--color-text-secondary)' }}>{recipientName}</div>
      )}
      {shipmentPublicId && (
        <div className="text-[9px] mt-1 font-mono truncate" style={{ color: 'var(--color-text-muted)' }}>
          Envio: {shipmentPublicId.slice(0, 12)}...
        </div>
      )}
    </div>
  );
}
