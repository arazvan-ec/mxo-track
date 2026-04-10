import { useState } from 'react';
import { useAdminCustomers } from '@/api/hooks/useAdminCustomers';
import type { CustomerListItem } from '@/api/types';
import { ResponsiveDataTable, type ColumnDef, type ActionDef } from '@/components/data-table/ResponsiveDataTable';
import { FilterBar, type FilterChip } from '@/components/data-table/FilterBar';
import { Pagination } from '@/components/data-table/Pagination';

/* ── Render helpers ────────────────────────────────────────────────── */

function ActiveBadge(_: unknown, row: CustomerListItem) {
  return row.active ? (
    <span className="inline-flex rounded-full badge-green px-2 text-xs font-semibold leading-5">Activo</span>
  ) : (
    <span className="inline-flex rounded-full badge-red px-2 text-xs font-semibold leading-5">Inactivo</span>
  );
}

/* ── Column definitions ────────────────────────────────────────────── */

const columns: ColumnDef<CustomerListItem>[] = [
  { key: 'name', label: 'Nombre', priority: 'primary', mobile: 'title' },
  { key: 'email', label: 'Email', priority: 'primary', mobile: 'subtitle' },
  { key: 'phone', label: 'Teléfono', priority: 'primary', mobile: 'subtitle' },
  { key: 'active', label: 'Estado', priority: 'primary', mobile: 'badge', render: ActiveBadge },
  { key: 'address', label: 'Dirección', priority: 'secondary', mobile: 'detail' },
  { key: 'userCount', label: 'Usuarios', priority: 'secondary', mobile: 'detail' },
];

/* ── Page component ────────────────────────────────────────────────── */

export function AdminCustomersListPage() {
  const [page, setPage] = useState(1);
  const [active, setActive] = useState('');

  const { data, isLoading } = useAdminCustomers({
    page,
    active: active || undefined,
  });

  const actions = (row: CustomerListItem): ActionDef[] => [
    { label: 'Editar', href: `/admin/customers/${row.publicId}/edit`, color: 'text-blue-600' },
  ];

  const statusColorClass = (row: CustomerListItem) =>
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

  return (
    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
      {/* Header */}
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-2xl font-bold" style={{ color: 'var(--color-text-primary)' }}>Clientes</h1>
        <a
          href="/admin/customers/new"
          className="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500"
        >
          Nuevo cliente
        </a>
      </div>

      {/* Filters */}
      <FilterBar
        chips={activeChips}
        activeChip={active}
        onChipClick={handleChipClick}
      />

      {/* Table / Cards */}
      <ResponsiveDataTable
        columns={columns}
        data={data?.items ?? []}
        keyField="publicId"
        actions={actions}
        isLoading={isLoading}
        emptyMessage="No hay clientes registrados."
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
