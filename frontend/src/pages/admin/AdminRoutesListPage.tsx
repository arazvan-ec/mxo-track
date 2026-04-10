import { useState } from 'react';
import { useAdminRoutes, useRouteFilters } from '@/api/hooks/useAdminRoutes';
import type { RouteListItem } from '@/api/types';
import { ResponsiveDataTable, type ColumnDef, type ActionDef } from '@/components/data-table/ResponsiveDataTable';
import { FilterBar, type FilterChip } from '@/components/data-table/FilterBar';
import { Pagination } from '@/components/data-table/Pagination';

/* ── Status helpers ────────────────────────────────────────────────── */

const STATUS_CONFIG: Record<string, { label: string; chipColor: string; badgeClass: string; borderClass: string }> = {
  PLANNED: { label: 'Planificada', chipColor: 'border-blue-500 text-blue-600', badgeClass: 'badge-blue', borderClass: 'border-l-blue-500' },
  ACTIVE: { label: 'Activa', chipColor: 'border-amber-500 text-amber-600', badgeClass: 'badge-amber', borderClass: 'border-l-amber-500' },
  DONE: { label: 'Completada', chipColor: 'border-green-500 text-green-600', badgeClass: 'badge-green', borderClass: 'border-l-green-500' },
  CANCELLED: { label: 'Cancelada', chipColor: 'border-red-500 text-red-600', badgeClass: 'badge-red', borderClass: 'border-l-red-500' },
};

function StatusBadge(_: unknown, row: RouteListItem) {
  const cfg = STATUS_CONFIG[row.status];
  return (
    <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${cfg?.badgeClass ?? 'badge-gray'}`}>
      {cfg?.label ?? row.status}
    </span>
  );
}

function ProgressBar(_: unknown, row: RouteListItem) {
  if (row.totalStops === 0) {
    return <span className="text-xs" style={{ color: 'var(--color-text-muted)' }}>Sin paradas</span>;
  }
  const pct = Math.round((row.deliveredStops / row.totalStops) * 100);
  return (
    <div className="flex items-center gap-2">
      <div className="w-24 rounded-full h-2" style={{ background: 'var(--color-surface)' }}>
        <div className="bg-green-500 h-2 rounded-full" style={{ width: `${pct}%` }} />
      </div>
      <span className="text-xs" style={{ color: 'var(--color-text-secondary)' }}>
        {row.deliveredStops}/{row.totalStops}
      </span>
    </div>
  );
}

function VehicleSubtitle(_: unknown, row: RouteListItem) {
  return (
    <span className="flex items-center gap-1">
      <span>🚚</span> {row.vehicleName ?? '-'}
    </span>
  );
}

function DriverSubtitle(_: unknown, row: RouteListItem) {
  return (
    <span className="flex items-center gap-1">
      <span>👤</span> {row.driverName ?? row.driverEmail ?? '-'}
    </span>
  );
}

/* ── Column definitions ────────────────────────────────────────────── */

const columns: ColumnDef<RouteListItem>[] = [
  { key: 'name', label: 'Nombre', priority: 'primary', mobile: 'title' },
  { key: 'vehicleName', label: 'Vehículo', priority: 'primary', mobile: 'subtitle', render: VehicleSubtitle },
  { key: 'driverName', label: 'Transportista', priority: 'primary', mobile: 'subtitle', render: DriverSubtitle },
  { key: 'status', label: 'Estado', priority: 'primary', mobile: 'badge', render: StatusBadge },
  { key: 'progress', label: 'Progreso', priority: 'primary', mobile: 'detail', render: ProgressBar },
  { key: 'customerName', label: 'Cliente', priority: 'secondary', mobile: 'detail' },
];

/* ── Page component ────────────────────────────────────────────────── */

export function AdminRoutesListPage() {
  const [status, setStatus] = useState('');
  const [page, setPage] = useState(1);
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [driverId, setDriverId] = useState('');
  const [customerId, setCustomerId] = useState('');

  const { data, isLoading } = useAdminRoutes({
    page,
    status: status || undefined,
    date_from: dateFrom || undefined,
    date_to: dateTo || undefined,
    driver: driverId || undefined,
    customer: customerId || undefined,
  });
  const { data: filters } = useRouteFilters();

  const filterChips: FilterChip[] = [
    { key: '', label: 'Todas', color: 'border-blue-500 text-blue-600' },
    { key: 'PLANNED', label: 'Planificadas', color: 'border-blue-500 text-blue-600' },
    { key: 'ACTIVE', label: 'Activas', color: 'border-amber-500 text-amber-600' },
    { key: 'DONE', label: 'Completadas', color: 'border-green-500 text-green-600' },
    { key: 'CANCELLED', label: 'Canceladas', color: 'border-red-500 text-red-600' },
  ];

  const actions = (row: RouteListItem): ActionDef[] => [
    { label: 'Ver', href: `/app/admin/routes/${row.publicId}`, color: 'text-emerald-600' },
    { label: 'Editar', href: `/admin/routes/${row.publicId}/edit`, color: 'text-blue-600' },
    { label: 'Análisis', href: `/app/admin/routes/${row.publicId}/analysis`, color: 'text-emerald-600', hidden: row.status !== 'DONE' },
  ];

  const statusColorClass = (row: RouteListItem) =>
    STATUS_CONFIG[row.status]?.borderClass ?? 'border-l-gray-500';

  const handleChipClick = (key: string) => {
    setStatus(key);
    setPage(1);
  };

  const advancedFilters = (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div>
        <label htmlFor="date_from" className="block text-xs font-medium mb-1" style={{ color: 'var(--color-text-secondary)' }}>Fecha desde</label>
        <input
          type="date" id="date_from" value={dateFrom}
          onChange={(e) => { setDateFrom(e.target.value); setPage(1); }}
          className="block w-full rounded-md border px-3 py-2 text-sm shadow-sm"
          style={{ borderColor: 'var(--color-border)' }}
        />
      </div>
      <div>
        <label htmlFor="date_to" className="block text-xs font-medium mb-1" style={{ color: 'var(--color-text-secondary)' }}>Fecha hasta</label>
        <input
          type="date" id="date_to" value={dateTo}
          onChange={(e) => { setDateTo(e.target.value); setPage(1); }}
          className="block w-full rounded-md border px-3 py-2 text-sm shadow-sm"
          style={{ borderColor: 'var(--color-border)' }}
        />
      </div>
      <div>
        <label htmlFor="driver" className="block text-xs font-medium mb-1" style={{ color: 'var(--color-text-secondary)' }}>Transportista</label>
        <select
          id="driver" value={driverId}
          onChange={(e) => { setDriverId(e.target.value); setPage(1); }}
          className="block w-full rounded-md border px-3 py-2 text-sm shadow-sm"
          style={{ borderColor: 'var(--color-border)' }}
        >
          <option value="">Todos</option>
          {filters?.drivers.map((d) => (
            <option key={d.id} value={d.id}>{d.name}</option>
          ))}
        </select>
      </div>
      <div>
        <label htmlFor="customer" className="block text-xs font-medium mb-1" style={{ color: 'var(--color-text-secondary)' }}>Cliente</label>
        <select
          id="customer" value={customerId}
          onChange={(e) => { setCustomerId(e.target.value); setPage(1); }}
          className="block w-full rounded-md border px-3 py-2 text-sm shadow-sm"
          style={{ borderColor: 'var(--color-border)' }}
        >
          <option value="">Todos</option>
          {filters?.customers.map((c) => (
            <option key={c.id} value={c.id}>{c.name}</option>
          ))}
        </select>
      </div>
    </div>
  );

  return (
    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
      {/* Header */}
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-2xl font-bold" style={{ color: 'var(--color-text-primary)' }}>Rutas</h1>
        <a
          href="/admin/routes/new"
          className="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500"
        >
          Nueva ruta
        </a>
      </div>

      {/* Filters */}
      <FilterBar
        chips={filterChips}
        activeChip={status}
        onChipClick={handleChipClick}
        advancedFilters={advancedFilters}
        advancedFiltersOpen={!!(dateFrom || dateTo || driverId || customerId)}
      />

      {/* Table / Cards */}
      <ResponsiveDataTable
        columns={columns}
        data={data?.items ?? []}
        keyField="publicId"
        actions={actions}
        isLoading={isLoading}
        emptyMessage="No hay rutas registradas."
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
