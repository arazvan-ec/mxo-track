import { useState, useRef, useEffect } from 'react';
import { useTheme, ALL_PRESETS, type ThemePreset } from '@/context/ThemeProvider';

const PRESET_META: Record<ThemePreset, { label: string; icon: string; colors: [string, string, string] }> = {
  default: { label: 'Default', icon: '○', colors: ['#0d9488', '#f8fafc', '#e2e8f0'] },
  glass: { label: 'Glass', icon: '◎', colors: ['#0d9488', '#e8edf5', 'rgba(255,255,255,0.5)'] },
  command: { label: 'Command', icon: '⬡', colors: ['#22d3ee', '#060a14', '#0c1425'] },
  bento: { label: 'Bento', icon: '▣', colors: ['#7c3aed', '#faf8f6', '#f0eaff'] },
  dense: { label: 'Dense', icon: '▤', colors: ['#0d9488', '#f1f3f5', '#dee2e6'] },
};

function PresetSwatch({ preset, active, onClick }: { preset: ThemePreset; active: boolean; onClick: () => void }) {
  const meta = PRESET_META[preset];
  return (
    <button
      type="button"
      onClick={onClick}
      className="flex items-center gap-3 w-full px-3 py-2 rounded-lg text-left transition-colors"
      style={{
        backgroundColor: active ? 'var(--color-accent-muted)' : 'transparent',
        color: 'var(--color-text-primary)',
      }}
    >
      <div className="flex gap-0.5">
        {meta.colors.map((c, i) => (
          <div
            key={i}
            className="w-3 h-3 rounded-full"
            style={{
              backgroundColor: c,
              border: '1px solid var(--color-border)',
            }}
          />
        ))}
      </div>
      <span className="text-sm font-medium flex-1">{meta.label}</span>
      {active && (
        <svg className="w-4 h-4" style={{ color: 'var(--color-accent)' }} fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
        </svg>
      )}
    </button>
  );
}

export function ThemeSwitcher() {
  const { preset, setPreset, resolved, toggle } = useTheme();
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  // Close on outside click
  useEffect(() => {
    if (!open) return;
    const handler = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [open]);

  return (
    <div ref={ref} className="fixed bottom-6 right-6 z-50">
      {/* Popover */}
      {open && (
        <div
          className="absolute bottom-14 right-0 w-56 p-2 theme-card"
          style={{ backgroundColor: 'var(--color-surface-elevated)' }}
        >
          <div className="px-2 py-1.5 mb-1">
            <span className="text-xs font-semibold uppercase tracking-wider" style={{ color: 'var(--color-text-muted)' }}>
              Tema visual
            </span>
          </div>
          {ALL_PRESETS.map((p) => (
            <PresetSwatch
              key={p}
              preset={p}
              active={preset === p}
              onClick={() => { setPreset(p); }}
            />
          ))}

          <div className="my-2 border-t" style={{ borderColor: 'var(--color-border)' }} />

          {/* Light/Dark toggle */}
          <button
            type="button"
            onClick={toggle}
            className="flex items-center gap-3 w-full px-3 py-2 rounded-lg text-left transition-colors"
            style={{ color: 'var(--color-text-primary)' }}
          >
            <span className="text-base">{resolved === 'dark' ? '☀' : '☾'}</span>
            <span className="text-sm font-medium">
              {resolved === 'dark' ? 'Modo claro' : 'Modo oscuro'}
            </span>
          </button>
        </div>
      )}

      {/* Floating button */}
      <button
        type="button"
        onClick={() => setOpen(!open)}
        className="flex items-center justify-center w-12 h-12 rounded-full shadow-lg transition-all hover:scale-105 active:scale-95"
        style={{
          background: 'var(--color-accent)',
          color: '#fff',
          boxShadow: '0 4px 20px rgba(0,0,0,0.2)',
        }}
        title="Cambiar tema visual"
      >
        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" d="M4.098 19.902a3.75 3.75 0 005.304 0l6.401-6.402M6.75 21A3.75 3.75 0 013 17.25V4.125C3 3.504 3.504 3 4.125 3h5.25c.621 0 1.125.504 1.125 1.125v4.072M6.75 21a3.75 3.75 0 003.75-3.75V8.197M6.75 21h13.125c.621 0 1.125-.504 1.125-1.125v-5.25c0-.621-.504-1.125-1.125-1.125h-4.072M10.5 8.197l2.88-2.88c.438-.439 1.15-.439 1.59 0l3.712 3.713c.44.44.44 1.152 0 1.59l-2.879 2.88M6.75 17.25h.008v.008H6.75v-.008z" />
        </svg>
      </button>
    </div>
  );
}
