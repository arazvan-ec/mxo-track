/* ── Types ─────────────────────────────────────────────────────────── */

export interface PaginationProps {
  page: number;
  totalPages: number;
  onPageChange: (page: number) => void;
}

/* ── Component ─────────────────────────────────────────────────────── */

export function Pagination({ page, totalPages, onPageChange }: PaginationProps) {
  if (totalPages <= 1) return null;

  return (
    <nav className="mt-6 flex items-center justify-between">
      <div className="text-sm" style={{ color: 'var(--color-text-secondary)' }}>
        Página {page} de {totalPages}
      </div>
      <div className="flex space-x-2">
        {page > 1 && (
          <button
            onClick={() => onPageChange(page - 1)}
            className="theme-card inline-flex items-center px-3 py-2 text-sm font-medium"
            style={{ color: 'var(--color-text-secondary)' }}
          >
            Anterior
          </button>
        )}
        {page < totalPages && (
          <button
            onClick={() => onPageChange(page + 1)}
            className="theme-card inline-flex items-center px-3 py-2 text-sm font-medium"
            style={{ color: 'var(--color-text-secondary)' }}
          >
            Siguiente
          </button>
        )}
      </div>
    </nav>
  );
}
