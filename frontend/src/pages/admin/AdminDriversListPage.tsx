import { useState } from 'react';
import { useAdminDrivers } from '@/api/hooks/useAdminDrivers';
import type { DriverListItem } from '@/api/types';
import { ResponsiveDataTable, type ColumnDef, type ActionDef } from '@/components/data-table/ResponsiveDataTable';
import { Pagination } from '@/components/data-table/Pagination';

/* ── Render helpers ────────────────────────────────────────────────── */

function ActiveBadge(_: unknown, row: DriverListItem) {
  return row.active ? (
    <span className="inline-flex rounded-full badge-green px-2 text-xs font-semibold leading-5">Activo</span>
  ) : (
    <span className="inline-flex rounded-full badge-red px-2 text-xs font-semibold leading-5">Inactivo</span>
  );
}

function NameOrEmail(_: unknown, row: DriverListItem) {
  return <>{row.name || row.email}</>;
}

function DateDisplay(value: unknown) {
  if (!value) return '-';
  const d = new Date(value as string);
  return d.toLocaleDateString('es', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

/* ── Column definitions ────────────────────────────────────────────── */

const columns: ColumnDef<DriverListItem>[] = [
  { key: 'name', label: 'Nombre', priority: 'primary', mobile: 'title', render: NameOrEmail },
  { key: 'email', label: 'Email', priority: 'primary', mobile: 'subtitle' },
  { key: 'active', label: 'Estado', priority: 'primary', mobile: 'badge', render: ActiveBadge },
  { key: 'createdAt', label: 'Creado', priority: 'secondary', mobile: 'hidden', render: DateDisplay },
];

/* ── Page component ────────────────────────────────────────────────── */

export function AdminDriversListPage() {
  const [page, setPage] = useState(1);

  const { data, isLoading } = useAdminDrivers({ page });

  const actions = (row: DriverListItem): ActionDef[] => [
    { label: 'Horario', href: `/admin/drivers/${row.publicId}/availability`, color: 'text-green-600' },
    { label: 'Editar', href: `/admin/drivers/${row.publicId}/edit`, color: 'text-blue-600' },
  ];

  const statusColorClass = (row: DriverListItem) =>
    row.active ? 'border-l-green-500' : 'border-l-red-500';

  return (
    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
      {/* Header */}
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-2xl font-bold" style={{ color: 'var(--color-text-primary)' }}>Conductores</h1>
        <a
          href="/admin/drivers/new"
          className="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500"
        >
          Nuevo conductor
        </a>
      </div>

      {/* Table / Cards */}
      <ResponsiveDataTable
        columns={columns}
        data={data?.items ?? []}
        keyField="publicId"
        actions={actions}
        isLoading={isLoading}
        emptyMessage="No hay conductores registrados."
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
