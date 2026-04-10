import { useState } from 'react';
import { useAdminVehicles } from '@/api/hooks/useAdminVehicles';
import type { VehicleListItem } from '@/api/types';
import { ResponsiveDataTable, type ColumnDef, type ActionDef } from '@/components/data-table/ResponsiveDataTable';
import { FilterBar, type FilterChip } from '@/components/data-table/FilterBar';
import { Pagination } from '@/components/data-table/Pagination';

/* ── Render helpers ────────────────────────────────────────────────── */

function ActiveBadge(_: unknown, row: VehicleListItem) {
  return row.active ? (
    <span className="inline-flex rounded-full badge-green px-2 text-xs font-semibold leading-5">Activo</span>
  ) : (
    <span className="inline-flex rounded-full badge-red px-2 text-xs font-semibold leading-5">Inactivo</span>
  );
}

function CapacityDisplay(_: unknown, row: VehicleListItem) {
  const parts: string[] = [];
  if (row.maxWeightKg) parts.push(`${row.maxWeightKg} kg`);
  if (row.maxVolumeM3) parts.push(`${row.maxVolumeM3} m³`);
  if (row.maxParcels) parts.push(`${row.maxParcels} paq.`);

  if (parts.length === 0) {
    return <span style={{ color: 'var(--color-text-muted)' }}>Sin configurar</span>;
  }
  return <span className="text-xs">{parts.join(' · ')}</span>;
}

function LastPositionDisplay(_: unknown, row: VehicleListItem) {
  if (!row.lastPosition) {
    return <span style={{ color: 'var(--color-text-muted)' }}>Sin señal</span>;
  }
  return (
    <span className="text-xs">
      {row.lastPosition.lat.toFixed(5)}, {row.lastPosition.lng.toFixed(5)}
    </span>
  );
}

function DateDisplay(value: unknown) {
  if (!value) return '-';
  const d = new Date(value as string);
  return d.toLocaleDateString('es', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

/* ── Column definitions ────────────────────────────────────────────── */

const columns: ColumnDef<VehicleListItem>[] = [
  { key: 'name', label: 'Nombre', priority: 'primary', mobile: 'title' },
  { key: 'active', label: 'Estado', priority: 'primary', mobile: 'badge', render: ActiveBadge },
  { key: 'capacity', label: 'Capacidad', priority: 'primary', mobile: 'detail', render: CapacityDisplay },
  { key: 'traccarDeviceId', label: 'Traccar ID', priority: 'secondary', mobile: 'detail' },
  { key: 'lastPosition', label: 'Última posición', priority: 'secondary', mobile: 'detail', render: LastPositionDisplay },
  { key: 'createdAt', label: 'Creado', priority: 'secondary', mobile: 'hidden', render: DateDisplay },
];

/* ── Page component ────────────────────────────────────────────────── */

export function AdminVehiclesListPage() {
  const [page, setPage] = useState(1);
  const [active, setActive] = useState('');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');

  const { data, isLoading } = useAdminVehicles({
    page,
    active: active || undefined,
    date_from: dateFrom || undefined,
    date_to: dateTo || undefined,
  });

  const actions = (row: VehicleListItem): ActionDef[] => [
    { label: 'Editar', href: `/admin/vehicles/${row.publicId}/edit`, color: 'text-blue-600' },
  ];

  const statusColorClass = (row: VehicleListItem) =>
    row.active ? 'border-l-green-500' : 'border-l-red-500';

  const activeChips: FilterChip[] = [
    { key: '', label: 'Todos', color: 'border-blue-500 text-blue-600' },
    { key: 'true', label: 'Activos', color: 'border-green-500 text-green-600' },
    { key: 'false', label: 'Inactivos', color: 'border-red-500 text-red-600' },
  ];

  const handleChipClick = (key: string) => {
    setActive(key);
    setPage(1);
  };

  const advancedFilters = (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
    </div>
  );

  return (
    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
      {/* Header */}
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-2xl font-bold" style={{ color: 'var(--color-text-primary)' }}>Vehículos</h1>
        <a
          href="/admin/vehicles/new"
          className="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500"
        >
          Nuevo vehículo
        </a>
      </div>

      {/* Filters */}
      <FilterBar
        chips={activeChips}
        activeChip={active}
        onChipClick={handleChipClick}
        advancedFilters={advancedFilters}
        advancedFiltersOpen={!!(dateFrom || dateTo)}
      />

      {/* Table / Cards */}
      <ResponsiveDataTable
        columns={columns}
        data={data?.items ?? []}
        keyField="publicId"
        actions={actions}
        isLoading={isLoading}
        emptyMessage="No hay vehículos registrados."
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
