import { useState } from 'react';
import { useIsDesktop } from '@/hooks/useIsDesktop';

/* ── Column definition ─────────────────────────────────────────────── */

export interface ColumnDef<T> {
  key: string;
  label: string;
  priority: 'primary' | 'secondary';
  mobile: 'title' | 'subtitle' | 'badge' | 'detail' | 'hidden';
  render?: (value: unknown, row: T) => React.ReactNode;
  align?: 'left' | 'right' | 'center';
  /** CSS class for the badge color bar on mobile cards */
  badgeColorClass?: (row: T) => string;
}

export interface ActionDef {
  label: string;
  href?: string;
  onClick?: () => void;
  color?: string; // tailwind text color class
  confirm?: string;
  hidden?: boolean;
}

export interface ResponsiveDataTableProps<T> {
  columns: ColumnDef<T>[];
  data: T[];
  keyField: keyof T;
  actions?: (row: T) => ActionDef[];
  onRowClick?: (row: T) => void;
  emptyMessage?: string;
  isLoading?: boolean;
  /** Status color bar class for mobile cards (left border) */
  statusColorClass?: (row: T) => string;
}

/* ── Value accessor ────────────────────────────────────────────────── */

function getValue<T>(row: T, key: string): unknown {
  return (row as Record<string, unknown>)[key];
}

/* ── Desktop Table ─────────────────────────────────────────────────── */

function DesktopTable<T>({
  columns, data, keyField, actions, onRowClick, emptyMessage,
}: ResponsiveDataTableProps<T>) {
  return (
    <div className="theme-card overflow-x-auto">
      <table className="min-w-full divide-y" style={{ borderColor: 'var(--color-border)' }}>
        <thead style={{ background: 'var(--color-surface)' }}>
          <tr>
            {columns.map((col) => (
              <th
                key={col.key}
                scope="col"
                className={`px-6 py-3 text-xs font-medium uppercase tracking-wider ${
                  col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left'
                }`}
                style={{ color: 'var(--color-text-secondary)' }}
              >
                {col.label}
              </th>
            ))}
            {actions && (
              <th scope="col" className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider"
                  style={{ color: 'var(--color-text-secondary)' }}>
                Acciones
              </th>
            )}
          </tr>
        </thead>
        <tbody className="divide-y" style={{ borderColor: 'var(--color-border)', background: 'var(--color-surface-elevated)' }}>
          {data.length === 0 ? (
            <tr>
              <td
                colSpan={columns.length + (actions ? 1 : 0)}
                className="px-6 py-8 text-center text-sm"
                style={{ color: 'var(--color-text-secondary)' }}
              >
                {emptyMessage ?? 'No hay datos.'}
              </td>
            </tr>
          ) : (
            data.map((row) => (
              <tr
                key={String(row[keyField])}
                onClick={onRowClick ? () => onRowClick(row) : undefined}
                className={onRowClick ? 'cursor-pointer hover:opacity-80' : ''}
              >
                {columns.map((col) => (
                  <td
                    key={col.key}
                    className={`whitespace-nowrap px-6 py-4 text-sm ${
                      col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left'
                    }`}
                    style={{ color: col.mobile === 'title' ? 'var(--color-text-primary)' : 'var(--color-text-secondary)' }}
                  >
                    {col.render ? col.render(getValue(row, col.key), row) : String(getValue(row, col.key) ?? '-')}
                  </td>
                ))}
                {actions && <ActionCell actions={actions(row)} />}
              </tr>
            ))
          )}
        </tbody>
      </table>
    </div>
  );
}

function ActionCell({ actions: rowActions }: { actions: ActionDef[] }) {
  return (
    <td className="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
      {rowActions.filter(a => !a.hidden).map((action) =>
        action.href ? (
          <a
            key={action.label}
            href={action.href}
            className={`${action.color ?? 'text-blue-600'} hover:opacity-80 mr-3`}
          >
            {action.label}
          </a>
        ) : (
          <button
            key={action.label}
            onClick={(e) => {
              e.stopPropagation();
              if (action.confirm && !window.confirm(action.confirm)) return;
              action.onClick?.();
            }}
            className={`${action.color ?? 'text-blue-600'} hover:opacity-80 mr-3`}
          >
            {action.label}
          </button>
        )
      )}
    </td>
  );
}

/* ── Mobile Cards ──────────────────────────────────────────────────── */

function MobileCards<T>({
  columns, data, keyField, actions, onRowClick, emptyMessage, statusColorClass,
}: ResponsiveDataTableProps<T>) {
  const [expandedKey, setExpandedKey] = useState<string | null>(null);

  const titleCol = columns.find(c => c.mobile === 'title');
  const subtitleCols = columns.filter(c => c.mobile === 'subtitle');
  const badgeCol = columns.find(c => c.mobile === 'badge');
  const detailCols = columns.filter(c => c.mobile === 'detail' && c.priority === 'primary');
  const secondaryCols = columns.filter(c => c.priority === 'secondary' && c.mobile !== 'hidden');

  if (data.length === 0) {
    return (
      <div className="text-center py-12 text-sm" style={{ color: 'var(--color-text-muted)' }}>
        {emptyMessage ?? 'No hay datos.'}
      </div>
    );
  }

  return (
    <div className="space-y-2 px-2">
      {data.map((row) => {
        const key = String(row[keyField]);
        const isExpanded = expandedKey === key;
        const colorClass = statusColorClass?.(row) ?? 'border-l-blue-500';
        const rowActions = actions?.(row).filter(a => !a.hidden) ?? [];

        return (
          <div
            key={key}
            className={`theme-card border-l-4 ${colorClass} transition-all`}
            style={{ padding: 0 }}
          >
            {/* Main card content — always visible */}
            <button
              type="button"
              className="w-full text-left p-3"
              onClick={() => {
                if (onRowClick && !isExpanded) {
                  onRowClick(row);
                } else {
                  setExpandedKey(isExpanded ? null : key);
                }
              }}
            >
              {/* Title + Badge row */}
              <div className="flex items-start justify-between gap-2">
                <span className="text-sm font-medium truncate" style={{ color: 'var(--color-text-primary)' }}>
                  {titleCol?.render
                    ? titleCol.render(getValue(row, titleCol.key), row)
                    : String(getValue(row, titleCol?.key ?? '') ?? '')}
                </span>
                {badgeCol && (
                  <span className="shrink-0">
                    {badgeCol.render
                      ? badgeCol.render(getValue(row, badgeCol.key), row)
                      : String(getValue(row, badgeCol.key) ?? '')}
                  </span>
                )}
              </div>

              {/* Subtitles */}
              {subtitleCols.length > 0 && (
                <div className="mt-1 text-xs flex items-center gap-1.5 flex-wrap" style={{ color: 'var(--color-text-secondary)' }}>
                  {subtitleCols.map((col, i) => {
                    const val = getValue(row, col.key);
                    if (!val) return null;
                    return (
                      <span key={col.key} className="flex items-center gap-0.5">
                        {i > 0 && <span style={{ color: 'var(--color-text-muted)' }}>·</span>}
                        {col.render ? col.render(val, row) : String(val)}
                      </span>
                    );
                  })}
                </div>
              )}

              {/* Primary details */}
              {detailCols.map((col) => {
                const val = getValue(row, col.key);
                return (
                  <div key={col.key} className="mt-1.5">
                    {col.render ? col.render(val, row) : (
                      <span className="text-xs" style={{ color: 'var(--color-text-secondary)' }}>
                        {col.label}: {String(val ?? '-')}
                      </span>
                    )}
                  </div>
                );
              })}

              {/* Expand indicator */}
              {(secondaryCols.length > 0 || rowActions.length > 0) && (
                <div className="mt-1 flex justify-end">
                  <svg
                    className={`w-4 h-4 transition-transform ${isExpanded ? 'rotate-180' : ''}`}
                    fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"
                    style={{ color: 'var(--color-text-muted)' }}
                  >
                    <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                  </svg>
                </div>
              )}
            </button>

            {/* Expanded content */}
            {isExpanded && (
              <div className="px-3 pb-3 border-t" style={{ borderColor: 'var(--color-border)' }}>
                {/* Secondary columns */}
                {secondaryCols.length > 0 && (
                  <div className="mt-2 space-y-1">
                    {secondaryCols.map((col) => {
                      const val = getValue(row, col.key);
                      return (
                        <div key={col.key} className="flex justify-between text-xs">
                          <span style={{ color: 'var(--color-text-muted)' }}>{col.label}</span>
                          <span style={{ color: 'var(--color-text-secondary)' }}>
                            {col.render ? col.render(val, row) : String(val ?? '-')}
                          </span>
                        </div>
                      );
                    })}
                  </div>
                )}

                {/* Actions */}
                {rowActions.length > 0 && (
                  <div className="mt-3 flex flex-wrap gap-2">
                    {rowActions.map((action) =>
                      action.href ? (
                        <a
                          key={action.label}
                          href={action.href}
                          className={`px-3 py-1.5 rounded-md text-xs font-medium ${action.color ?? 'text-blue-600'}`}
                          style={{ background: 'var(--color-surface)' }}
                        >
                          {action.label}
                        </a>
                      ) : (
                        <button
                          key={action.label}
                          onClick={() => {
                            if (action.confirm && !window.confirm(action.confirm)) return;
                            action.onClick?.();
                          }}
                          className={`px-3 py-1.5 rounded-md text-xs font-medium ${action.color ?? 'text-blue-600'}`}
                          style={{ background: 'var(--color-surface)' }}
                        >
                          {action.label}
                        </button>
                      )
                    )}
                  </div>
                )}
              </div>
            )}
          </div>
        );
      })}
    </div>
  );
}

/* ── Main Export ────────────────────────────────────────────────────── */

export function ResponsiveDataTable<T>(props: ResponsiveDataTableProps<T>) {
  const isDesktop = useIsDesktop();

  if (props.isLoading) {
    return (
      <div className="text-center py-12 text-sm" style={{ color: 'var(--color-text-muted)' }}>
        Cargando...
      </div>
    );
  }

  return isDesktop ? <DesktopTable {...props} /> : <MobileCards {...props} />;
}
