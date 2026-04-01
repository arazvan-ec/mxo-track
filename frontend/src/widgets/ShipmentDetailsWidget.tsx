import type { WidgetProps } from './types';

interface ShipmentDetailsData {
  shipment?: {
    publicId: string;
    address: string;
    status: string;
    recipientName?: string;
    recipientPhone?: string;
    priority?: string;
  } | null;
}

const STATUS_COLORS: Record<string, string> = {
  PENDING: '#3B82F6',
  IN_TRANSIT: '#F59E0B',
  DELIVERED: '#10B981',
  EXCEPTION: '#EF4444',
  RETURNED: '#9CA3AF',
};

export function ShipmentDetailsWidget({ data, expanded }: WidgetProps) {
  const { shipment } = data as ShipmentDetailsData;
  if (!shipment) return null;

  const statusColor = STATUS_COLORS[shipment.status] ?? '#6B7280';

  return (
    <div className="px-4 pb-3">
      <div
        className="rounded-lg p-3 border"
        style={{
          backgroundColor: 'color-mix(in srgb, var(--color-surface-elevated) 60%, transparent)',
          borderColor: 'var(--color-border-subtle)',
        }}
      >
        <div className="text-[10px] uppercase tracking-wider mb-2" style={{ color: 'var(--color-text-muted)' }}>
          Shipment
        </div>

        <div className="flex items-center justify-between mb-2">
          <span className="text-xs font-mono truncate" style={{ color: 'var(--color-text-secondary)' }}>
            {shipment.publicId.slice(0, 12)}...
          </span>
          <span
            className="text-[10px] font-semibold uppercase px-1.5 py-0.5 rounded"
            style={{ color: statusColor, backgroundColor: `${statusColor}20` }}
          >
            {shipment.status}
          </span>
        </div>

        <div className="text-sm mb-1 line-clamp-2" style={{ color: 'var(--color-text-primary)' }}>
          {shipment.address}
        </div>

        {expanded && (
          <div className="space-y-1 mt-2 text-xs" style={{ color: 'var(--color-text-secondary)' }}>
            {shipment.recipientName && <div>Recipient: {shipment.recipientName}</div>}
            {shipment.recipientPhone && <div>Phone: {shipment.recipientPhone}</div>}
            {shipment.priority && (
              <div>
                Priority: <span className="font-medium">{shipment.priority}</span>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
