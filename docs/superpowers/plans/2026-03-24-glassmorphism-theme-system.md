# Plan: Glassmorphism Theme System + Visual Redesign

**Spec:** `docs/superpowers/specs/2026-03-24-glassmorphism-theme-system-design.md`
**Branch:** `claude/fix-map-centering-sheet-nbZRd`
**Goal:** Add dark/light theme system with glassmorphism BottomSheet, teal accent, and themed map.

---

## Architecture

```
index.css (design tokens)
    ↓
ThemeProvider (context)  ─── toggles .dark class on <html>
    ↓                        ↓
Components (use CSS vars)   MapCanvas (re-applies style on theme change)
```

## Tasks — Parallel Execution Design

### Group A: Foundation (independent, parallelizable)

#### Task 1: Design tokens in index.css
**File:** `frontend/src/index.css`
**Changes:**
- Add `@theme` block for Tailwind v4 custom colors (if supported) OR define CSS variables directly
- Add `:root` block with light theme token values
- Add `.dark` block with dark theme token values
- Migrate MapLibre popup/control overrides to use CSS variables

**Complete replacement for index.css:**
```css
@import "tailwindcss";

/* ── Design Tokens ── */
:root {
  --color-surface: #f8fafc;
  --color-surface-elevated: #ffffff;
  --color-surface-glass: rgba(255, 255, 255, 0.80);
  --color-surface-overlay: rgba(0, 0, 0, 0.50);
  --color-text-primary: #0f172a;
  --color-text-secondary: #64748b;
  --color-text-muted: #94a3b8;
  --color-border: #e2e8f0;
  --color-border-subtle: rgba(226, 232, 240, 0.50);
  --color-border-accent: rgba(94, 234, 212, 0.30);
  --color-accent: #0d9488;
  --color-accent-bg: #14b8a6;
  --color-accent-hover: #0f766e;
  --color-accent-muted: rgba(20, 184, 166, 0.15);
  --color-success: #10b981;
  --color-error: #ef4444;
  --color-warning: #f59e0b;
  --map-background: #e8ecf0;
  --map-water: #bfdbfe;
  --map-earth: #f1f5f9;
  --map-highway: #94a3b8;
  --map-major: #cbd5e1;
  --map-minor: #e2e8f0;
  --map-buildings: #dde3ea;
  --map-park: #bbf7d0;
}

.dark {
  --color-surface: #020617;
  --color-surface-elevated: #0f172a;
  --color-surface-glass: rgba(15, 23, 42, 0.80);
  --color-surface-overlay: rgba(0, 0, 0, 0.60);
  --color-text-primary: #f1f5f9;
  --color-text-secondary: #94a3b8;
  --color-text-muted: #64748b;
  --color-border: #334155;
  --color-border-subtle: rgba(51, 65, 85, 0.40);
  --color-border-accent: rgba(45, 212, 191, 0.30);
  --color-accent: #2dd4bf;
  --color-accent-bg: #14b8a6;
  --color-accent-hover: #5eead4;
  --color-accent-muted: rgba(20, 184, 166, 0.15);
  --color-success: #34d399;
  --color-error: #f87171;
  --color-warning: #fbbf24;
  --map-background: #0f172a;
  --map-water: #1e293b;
  --map-earth: #0f172a;
  --map-highway: #475569;
  --map-major: #334155;
  --map-minor: #1e293b;
  --map-buildings: #1a2332;
  --map-park: #0d1f1a;
}

body {
  margin: 0;
  background-color: var(--color-surface);
  color: var(--color-text-primary);
  transition: background-color 0.2s, color 0.2s;
}

#root {
  width: 100%;
  height: 100vh;
  display: flex;
}

/* MapLibre popups — themed */
.maplibregl-popup-content {
  background: var(--color-surface-glass) !important;
  color: var(--color-text-primary) !important;
  border-radius: 10px !important;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2) !important;
  border: 1px solid var(--color-border-accent) !important;
  padding: 12px 14px !important;
  backdrop-filter: blur(12px) !important;
  -webkit-backdrop-filter: blur(12px) !important;
}

.maplibregl-popup-tip {
  border-top-color: var(--color-surface-glass) !important;
}

.maplibregl-popup-close-button {
  color: var(--color-text-secondary) !important;
  font-size: 18px !important;
  padding: 2px 6px !important;
}

.maplibregl-popup-close-button:hover {
  color: var(--color-text-primary) !important;
  background: transparent !important;
}

/* MapLibre zoom controls — themed */
.maplibregl-ctrl-group {
  background: var(--color-surface-glass) !important;
  border: 1px solid var(--color-border-subtle) !important;
  backdrop-filter: blur(12px) !important;
  -webkit-backdrop-filter: blur(12px) !important;
}

.maplibregl-ctrl-group button {
  background: transparent !important;
  color: var(--color-text-primary) !important;
}

.maplibregl-ctrl-group button:hover {
  background: var(--color-accent-muted) !important;
  color: var(--color-accent) !important;
}

.maplibregl-ctrl-group button + button {
  border-top-color: var(--color-border-subtle) !important;
}

/* Navigation sidebar slide-in animation */
@keyframes slide-in-left {
  from { transform: translateX(-100%); }
  to { transform: translateX(0); }
}

.animate-slide-in-left {
  animation: slide-in-left 0.2s ease-out;
}
```

**Acceptance Criteria:**
- [ ] `:root` has all light tokens, `.dark` has all dark tokens
- [ ] MapLibre popup/control classes use CSS vars
- [ ] body uses `var(--color-surface)` background
- [ ] TypeScript compiles (no TS in CSS, just verify app still works)

---

#### Task 2: ThemeProvider context
**File:** `frontend/src/context/ThemeProvider.tsx` (NEW)
**Also modify:** `frontend/src/main.tsx`

**ThemeProvider.tsx:**
```tsx
import { createContext, useContext, useEffect, useState, useCallback, type ReactNode } from 'react';

type ThemeMode = 'light' | 'dark' | 'system';
type ResolvedTheme = 'light' | 'dark';

interface ThemeContextValue {
  mode: ThemeMode;
  resolved: ResolvedTheme;
  setMode: (mode: ThemeMode) => void;
  toggle: () => void;
}

const ThemeContext = createContext<ThemeContextValue | null>(null);

const STORAGE_KEY = 'mxo-theme';

function getSystemTheme(): ResolvedTheme {
  if (typeof window === 'undefined') return 'dark';
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function resolveTheme(mode: ThemeMode): ResolvedTheme {
  return mode === 'system' ? getSystemTheme() : mode;
}

export function ThemeProvider({ children }: { children: ReactNode }) {
  const [mode, setModeState] = useState<ThemeMode>(() => {
    if (typeof window === 'undefined') return 'system';
    return (localStorage.getItem(STORAGE_KEY) as ThemeMode) ?? 'system';
  });

  const resolved = resolveTheme(mode);

  const setMode = useCallback((m: ThemeMode) => {
    setModeState(m);
    localStorage.setItem(STORAGE_KEY, m);
  }, []);

  const toggle = useCallback(() => {
    setMode(resolved === 'dark' ? 'light' : 'dark');
  }, [resolved, setMode]);

  // Apply dark class to <html>
  useEffect(() => {
    const root = document.documentElement;
    if (resolved === 'dark') {
      root.classList.add('dark');
    } else {
      root.classList.remove('dark');
    }
  }, [resolved]);

  // Listen for system theme changes
  useEffect(() => {
    if (mode !== 'system') return;
    const mq = window.matchMedia('(prefers-color-scheme: dark)');
    const handler = () => setModeState('system'); // re-trigger resolve
    mq.addEventListener('change', handler);
    return () => mq.removeEventListener('change', handler);
  }, [mode]);

  return (
    <ThemeContext.Provider value={{ mode, resolved, setMode, toggle }}>
      {children}
    </ThemeContext.Provider>
  );
}

export function useTheme(): ThemeContextValue {
  const ctx = useContext(ThemeContext);
  if (!ctx) throw new Error('useTheme must be used within ThemeProvider');
  return ctx;
}
```

**main.tsx change:** Wrap `RouterProvider` with `<ThemeProvider>`:
```tsx
import { ThemeProvider } from './context/ThemeProvider';

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <ThemeProvider>
      <QueryClientProvider client={queryClient}>
        <RouterProvider router={router} />
      </QueryClientProvider>
    </ThemeProvider>
  </StrictMode>,
);
```

**Acceptance Criteria:**
- [ ] ThemeProvider manages 'light' | 'dark' | 'system' modes
- [ ] Persists to localStorage
- [ ] Applies `.dark` class on `<html>`
- [ ] `useTheme()` hook available everywhere
- [ ] System preference listener works
- [ ] TypeScript compiles

---

#### Task 3: Map style theming
**Files:**
- `frontend/src/components/maps/styles/dark-style.ts` — refactor
- `frontend/src/components/maps/styles/light-style.ts` — NEW (or merge into one file)
- `frontend/src/components/maps/MapCanvas.tsx` — subscribe to theme

Refactor `dark-style.ts` → `map-style.ts` (or keep both and add shared util):

**Create `frontend/src/components/maps/styles/map-style.ts`:**
```tsx
import { layers, DARK, LIGHT, type Flavor } from '@protomaps/basemaps';
import type { StyleSpecification } from 'maplibre-gl';

const TILE_URL = 'https://maps.protomaps.com/tiles/v4/{z}/{x}/{y}.mvt';

const darkFlavor: Flavor = {
  ...DARK,
  background: '#0f172a',
  earth: '#0f172a',
  water: '#1e293b',
  highway: '#475569',
  major: '#334155',
  minor_a: '#1e293b',
  minor_b: '#1e293b',
  link: '#1e293b',
  buildings: '#1a2332',
  park_a: '#0d1f1a',
  park_b: '#0d1f1a',
  industrial: '#131c2a',
};

const lightFlavor: Flavor = {
  ...LIGHT,
  background: '#f1f5f9',
  earth: '#f1f5f9',
  water: '#bfdbfe',
  highway: '#94a3b8',
  major: '#cbd5e1',
  minor_a: '#e2e8f0',
  minor_b: '#e2e8f0',
  link: '#e2e8f0',
  buildings: '#dde3ea',
  park_a: '#bbf7d0',
  park_b: '#bbf7d0',
  industrial: '#e2e8f0',
};

export function createMapStyle(theme: 'light' | 'dark'): StyleSpecification {
  const flavor = theme === 'dark' ? darkFlavor : lightFlavor;
  const mapLayers = layers('protomaps', flavor, { lang: 'es' });

  return {
    version: 8,
    glyphs: 'https://cdn.protomaps.com/fonts/pbf/{fontstack}/{range}.pbf',
    sources: {
      protomaps: {
        type: 'vector',
        tiles: [TILE_URL],
        maxzoom: 15,
        attribution:
          '&copy; <a href="https://protomaps.com">Protomaps</a> &copy; <a href="https://openstreetmap.org">OpenStreetMap</a>',
      },
    },
    layers: mapLayers,
  };
}

export const FALLBACK_RASTER_STYLE: StyleSpecification = {
  version: 8,
  sources: {
    osm: {
      type: 'raster',
      tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
      tileSize: 256,
      attribution: '&copy; OpenStreetMap contributors',
    },
  },
  layers: [{ id: 'osm', type: 'raster', source: 'osm' }],
};
```

**MapCanvas.tsx changes:**
- Import `useTheme` from context
- Import `createMapStyle` instead of `createDarkStyle`
- On mount: use `createMapStyle(resolved)` instead of `createDarkStyle()`
- Add `useEffect` that listens to `resolved` theme and calls `map.setStyle(createMapStyle(resolved))` when it changes
- Keep all other logic (layers, flyTo, fitBounds) intact

**Important:** Read the FULL MapCanvas.tsx before modifying — it has refs, imperative handle, layer management. Only add theme subscription, don't break existing logic.

**Acceptance Criteria:**
- [ ] `createMapStyle('dark')` produces same style as current `createDarkStyle()`
- [ ] `createMapStyle('light')` produces a light-themed map
- [ ] MapCanvas switches style on theme change
- [ ] Existing layers/markers survive style change
- [ ] TypeScript compiles

---

### Group B: Component Migration (depends on Task 1 tokens being in CSS, but each is independent)

All tasks in this group use the same pattern: replace hardcoded Tailwind classes with CSS variable references. Each file is independent.

#### Task 4: BottomSheet glassmorphism
**File:** `frontend/src/components/bottom-sheet/BottomSheet.tsx`
**Changes to the outer div (line 22):**

FROM:
```
className="fixed left-0 right-0 top-0 z-40 flex flex-col bg-slate-900 rounded-t-2xl border-t border-slate-700 shadow-2xl"
```

TO:
```
className="fixed left-0 right-0 top-0 z-40 flex flex-col rounded-t-2xl border-t shadow-[0_-8px_32px_rgba(0,0,0,0.15)]"
style={{ backgroundColor: 'var(--color-surface-glass)', borderColor: 'var(--color-border-accent)', backdropFilter: 'blur(20px)', WebkitBackdropFilter: 'blur(20px)' }}
```

**Handle area (line 27-33):**
- `bg-slate-600` on drag handle → `bg-[var(--color-text-muted)]`
- `text-slate-200` on title → use `text-[var(--color-text-primary)]`

**Content area: loading/error states:**
- `text-blue-500` → `text-[var(--color-accent)]`
- `border-blue-500` → `border-[var(--color-accent)]`
- `text-slate-400` → `text-[var(--color-text-secondary)]`
- `text-red-400`/`text-red-500` → `text-[var(--color-error)]`

**Acceptance Criteria:**
- [ ] Glassmorphism effect visible (map shows through blurred)
- [ ] All hardcoded colors replaced with CSS vars
- [ ] TypeScript compiles

---

#### Task 5: TopBar theming + toggle button
**File:** `frontend/src/components/layout/TopBar.tsx`
**Changes:**

Outer div FROM:
```
className="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-x-4 border-b border-gray-200 bg-white px-4 shadow-sm sm:gap-x-6 sm:px-6 lg:px-8"
```
TO:
```
className="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-x-4 border-b px-4 shadow-sm sm:gap-x-6 sm:px-6 lg:px-8"
style={{ backgroundColor: 'var(--color-surface-glass)', borderColor: 'var(--color-border)', backdropFilter: 'blur(16px)', WebkitBackdropFilter: 'blur(16px)' }}
```

Hamburger button: `text-gray-500 hover:text-gray-900` → `text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]`

Divider: `bg-gray-200` → `bg-[var(--color-border)]`

**Add theme toggle button** in the right section, before LanguageSwitcher:
```tsx
import { useTheme } from '@/context/ThemeProvider';
// ...
const { resolved, toggle } = useTheme();
// In JSX, in the right section div:
<button onClick={toggle} className="p-2 rounded-lg transition-colors" style={{ color: 'var(--color-text-secondary)' }}>
  {resolved === 'dark' ? (
    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
      <path strokeLinecap="round" strokeLinejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
    </svg>
  ) : (
    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
      <path strokeLinecap="round" strokeLinejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
    </svg>
  )}
</button>
```

**Acceptance Criteria:**
- [ ] TopBar glassmorphism matches BottomSheet style
- [ ] Theme toggle button visible and functional
- [ ] Sun icon in dark mode, moon icon in light mode
- [ ] All hardcoded colors replaced
- [ ] TypeScript compiles

---

#### Task 6: NavigationSidebar theming
**File:** `frontend/src/components/layout/NavigationSidebar.tsx`
**Changes:**
- Read file first to see all color classes
- Replace `bg-slate-800`/`bg-slate-900` → surface-elevated
- Replace `text-white`/`text-slate-300`/`text-slate-400` → text vars
- Replace `border-slate-700` → border var
- Replace `bg-blue-600` active state → accent-bg var
- Replace `hover:bg-slate-700` → accent-muted var

**Acceptance Criteria:**
- [ ] Sidebar themed in both modes
- [ ] Active item uses teal accent
- [ ] TypeScript compiles

---

#### Task 7: Panel components theming (StopListPanel + RouteSummaryBar + RouteMetricsPanel + VehicleInfoPanel)
**Files:** All 4 panel files in `frontend/src/components/panels/`

**StopListPanel.tsx:**
- `bg-slate-800/50` → surface-elevated with opacity
- `border-slate-700/30` → border-subtle
- `text-slate-200` → text-primary
- `text-slate-400` → text-secondary
- `bg-blue-600/20` selected → accent-muted
- `border-blue-500/40` selected → border-accent
- `text-emerald-400` delivered → success var
- `text-slate-500` ETA → text-muted

**RouteSummaryBar.tsx:**
- `text-slate-400` → text-secondary
- `text-slate-300` → text-primary (count is important)
- `text-slate-600` → text-muted (dot separator)
- `text-blue-400` → accent (ETA)

**RouteMetricsPanel.tsx:**
- Read file first, same pattern: elevated surface + border-subtle + text hierarchy + emerald → success

**VehicleInfoPanel.tsx:**
- Same pattern

**Acceptance Criteria:**
- [ ] All 4 panels use CSS vars
- [ ] No hardcoded slate/blue/emerald classes remain (except STOP_STATUS_COLORS which are dynamic)
- [ ] TypeScript compiles

---

### Group C: Verification

#### Task 8: TypeScript + build + visual check
- Run `cd frontend && npx tsc --noEmit`
- Run `cd frontend && npx vite build`
- Fix any issues
- Commit and push

---

## Parallelization Map

```
Phase 1 (parallel):  Task 1 (tokens)  |  Task 2 (ThemeProvider)  |  Task 3 (map style)
                         ↓                    ↓                        ↓
Phase 2 (parallel):  Task 4 (BottomSheet) | Task 5 (TopBar) | Task 6 (Sidebar) | Task 7 (Panels)
                         ↓                    ↓                  ↓                   ↓
Phase 3 (sequential): Task 8 (verify all)
```

## File Structure

```
frontend/src/
├── index.css                              ← MODIFY (Task 1)
├── main.tsx                               ← MODIFY (Task 2)
├── context/
│   └── ThemeProvider.tsx                   ← NEW (Task 2)
├── components/
│   ├── bottom-sheet/
│   │   └── BottomSheet.tsx                ← MODIFY (Task 4)
│   ├── layout/
│   │   ├── TopBar.tsx                     ← MODIFY (Task 5)
│   │   └── NavigationSidebar.tsx          ← MODIFY (Task 6)
│   ├── panels/
│   │   ├── StopListPanel.tsx              ← MODIFY (Task 7)
│   │   ├── RouteSummaryBar.tsx            ← MODIFY (Task 7)
│   │   ├── RouteMetricsPanel.tsx          ← MODIFY (Task 7)
│   │   └── VehicleInfoPanel.tsx           ← MODIFY (Task 7)
│   └── maps/
│       ├── MapCanvas.tsx                  ← MODIFY (Task 3)
│       └── styles/
│           ├── dark-style.ts              ← DELETE (replaced by map-style.ts)
│           └── map-style.ts              ← NEW (Task 3)
```
