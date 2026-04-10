import { useState } from 'react';
import { useAdminShipments, useShipmentFilters } from '@/api/hooks/useAdminShipments';
import type { ShipmentListItem } from '@/api/types';
import { ResponsiveDataTable, type ColumnDef, type ActionDef } from '@/components/data-table/ResponsiveDataTable';
import { Pagination } from '@/components/data-table/Pagination';

/* ── Render helpers ────────────────────────────────────────────────── */

const PRIORITY_CONFIG: Record<string, { label: string; badgeClass: string; borderClass: string }> = {
  CRITICAL: { label: 'Crítico', badgeClass: 'badge-red', borderClass: 'border-l-red-500' },
  URGENT: { label: 'Urgente', badgeClass: 'badge-amber', borderClass: 'border-l-amber-500' },
  HIGH: { label: 'Alto', badgeClass: 'badge-orange', borderClass: 'border-l-orange-500' },
  NORMAL: { label: 'Normal', badgeClass: 'badge-blue', borderClass: 'border-l-blue-500' },
  LOW: { label: 'Bajo', badgeClass: 'badge-gray', borderClass: 'border-l-gray-500' },
};

function PriorityBadge(_: unknown, row: ShipmentListItem) {
  const cfg = PRIORITY_CONFIG[row.priority];
  return (
    <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${cfg?.badgeClass ?? 'badge-gray'}`}>
      {cfg?.label ?? row.priority}
    </span>
  );
}

function CargoDisplay(_: unknown, row: ShipmentListItem) {
  const parts: string[] = [];
  if (row.totalWeightKg) parts.push(`${row.totalWeightKg} kg`);
  if (row.totalVolumeM3) parts.push(`${row.totalVolumeM3} m³`);
  if (row.totalParcels) parts.push(`${row.totalParcels} bulto${row.totalParcels !== 1 ? 's' : ''}`);
  return <span className="text-xs">{parts.join(' · ') || '-'}</span>;
}

function DateDisplay(value: unknown) {
  if (!value) return '-';
  const d = new Date(value as string);
  return d.toLocaleDateString('es', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

/* ── Column definitions ────────────────────────────────────────────── */

const columns: ColumnDef<ShipmentListItem>[] = [
  { key: 'reference', label: 'Referencia', priority: 'primary', mobile: 'title' },
  { key: 'recipientName', label: 'Destinatario', priority: 'primary', mobile: 'subtitle' },
  { key: 'address', label: 'Dirección', priority: 'primary', mobile: 'subtitle' },
  { key: 'priority', label: 'Prioridad', priority: 'primary', mobile: 'badge', render: PriorityBadge },
  { key: 'cargo', label: 'Carga', priority: 'primary', mobile: 'detail', render: CargoDisplay },
  { key: 'customerName', label: 'Cliente', priority: 'secondary', mobile: 'detail' },
  { key: 'createdAt', label: 'Creado', priority: 'secondary', mobile: 'hidden', render: DateDisplay },
];

/* ── Page component ────────────────────────────────────────────────── */

export function AdminShipmentsListPage() {
  const [page, setPage] = useState(1);
  const [customerId, setCustomerId] = useState('');

  const { data, isLoading } = useAdminShipments({
    page,
    customer: customerId || undefined,
  });
  const { data: filters } = useShipmentFilters();

  const actions = (row: ShipmentListItem): ActionDef[] => [
    { label: 'Editar', href: `/admin/shipments/${row.publicId}/edit`, color: 'text-blue-600' },
  ];

  const statusColorClass = (row: ShipmentListItem) =>
    PRIORITY_CONFIG[row.priority]?.borderClass ?? 'border-l-gray-500';

  return (
    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
      {/* Header */}
      <div className="mb-6 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold" style={{ color: 'var(--color-text-primary)' }}>Envíos</h1>
          {data && (
            <p className="mt-1 text-sm" style={{ color: 'var(--color-text-secondary)' }}>
              {data.total} envío{data.total !== 1 ? 's' : ''} en total
            </p>
          )}
        </div>
        <a
          href="/admin/shipments/new"
          className="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500"
        >
          Nuevo envío
        </a>
      </div>

      {/* Customer filter */}
      <div className="mb-4 flex items-center gap-3">
        <label htmlFor="customer" className="text-sm font-medium" style={{ color: 'var(--color-text-secondary)' }}>
          Filtrar por cliente:
        </label>
        <select
          id="customer"
          value={customerId}
          onChange={(e) => { setCustomerId(e.target.value); setPage(1); }}
          className="rounded-md shadow-sm text-sm"
          style={{ borderColor: 'var(--color-border)' }}
        >
          <option value="">Todos los clientes</option>
          {filters?.customers.map((c) => (
            <option key={c.publicId} value={c.publicId}>{c.name}</option>
          ))}
        </select>
      </div>

      {/* Table / Cards */}
      <ResponsiveDataTable
        columns={columns}
        data={data?.items ?? []}
        keyField="publicId"
        actions={actions}
        isLoading={isLoading}
        emptyMessage="No hay envíos registrados."
        statusColorClass={statusColorClass}
      />

      {/* Pagination */}
      <Pagination
        page={data?.page ?? 1}
        totalPages={data?.pages ?? 1}
        onPageChange={setPage}
      />
    </div>
  );
}
