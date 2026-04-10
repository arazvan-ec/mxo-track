import { useState } from 'react';

/* ── Types ─────────────────────────────────────────────────────────── */

export interface FilterChip {
  key: string;
  label: string;
  color?: string; // CSS class for active state, e.g. 'border-blue-500 text-blue-600'
  count?: number;
}

export interface FilterBarProps {
  chips: FilterChip[];
  activeChip: string;
  onChipClick: (key: string) => void;
  /** Slot for advanced filter controls (rendered inside collapsible panel) */
  advancedFilters?: React.ReactNode;
  advancedFiltersOpen?: boolean;
}

/* ── Component ─────────────────────────────────────────────────────── */

export function FilterBar({ chips, activeChip, onChipClick, advancedFilters, advancedFiltersOpen: initialOpen }: FilterBarProps) {
  const [filtersOpen, setFiltersOpen] = useState(initialOpen ?? false);

  return (
    <div className="mb-4">
      {/* Chip bar — scrollable on mobile, sticky */}
      <div className="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-hide snap-x snap-mandatory">
        {chips.map((chip) => {
          const isActive = chip.key === activeChip;
          return (
            <button
              key={chip.key}
              onClick={() => onChipClick(chip.key)}
              className={`shrink-0 snap-start inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm font-medium transition-all border ${
                isActive
                  ? `${chip.color ?? 'border-blue-500 text-blue-600'} bg-blue-500/10`
                  : 'border-transparent hover:opacity-80'
              }`}
              style={isActive ? undefined : { color: 'var(--color-text-secondary)', background: 'var(--color-surface)' }}
            >
              {chip.label}
              {chip.count != null && (
                <span
                  className={`text-xs rounded-full px-1.5 py-0.5 font-semibold ${
                    isActive ? 'bg-blue-500/20' : ''
                  }`}
                  style={isActive ? undefined : { background: 'var(--color-surface-elevated)', color: 'var(--color-text-muted)' }}
                >
                  {chip.count}
                </span>
              )}
            </button>
          );
        })}
      </div>

      {/* Advanced filters toggle + panel */}
      {advancedFilters && (
        <div className="mt-2">
          <button
            onClick={() => setFiltersOpen(!filtersOpen)}
            type="button"
            className="inline-flex items-center gap-2 text-sm font-medium hover:opacity-80 transition-colors"
            style={{ color: 'var(--color-text-secondary)' }}
          >
            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
            </svg>
            Filtros avanzados
            <svg
              className={`h-4 w-4 transition-transform ${filtersOpen ? 'rotate-180' : ''}`}
              fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor"
            >
              <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </button>

          {filtersOpen && (
            <div
              className="mt-3 rounded-lg p-4 shadow ring-1"
              style={{ background: 'var(--color-surface-elevated)', borderColor: 'var(--color-border)' }}
            >
              {advancedFilters}
            </div>
          )}
        </div>
      )}
    </div>
  );
}
