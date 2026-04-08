# Plan — Dashboard Improvements (Post-Retrospective)

**Spec:** `docs/superpowers/specs/2026-04-08-dashboard-improvements-design.md`
**Branch:** `claude/innovative-dashboard-design-ddekk`

## Phase 1 (v0): Working implementation

### [parallel] Wave 1: Independent foundations

- **Task 1a:** Add `.theme-card-overlay` CSS class — File: `frontend/src/index.css`
  Add `.theme-card-overlay` with forced glass + blur, reading preset vars for radius/shadow.
  → produces: reusable CSS class for map-context widgets

- **Task 1b:** Adapt ThemeSwitcher for inline mode — File: `frontend/src/components/ui/ThemeSwitcher.tsx`
  Add `mode` prop ('floating' | 'inline'). Inline mode: relative positioning, no fixed, smaller button.
  → produces: ThemeSwitcher that works embedded in TopBar

- **Task 1c:** Write 4 UI component tests — Files: `frontend/src/components/ui/*.test.tsx`
  - `AnimatedCounter.test.tsx` — vi.useFakeTimers + RAF mock, verify target value
  - `SparklineSVG.test.tsx` — verify SVG output, min data edge case
  - `RadialGauge.test.tsx` — verify color thresholds, center text
  - `StaggeredEntrance.test.tsx` — verify animationDelay per child
  → produces: 4 test files, all passing

### Wave 2: TopBar integration (needs Task 1b)

- **Task 2a:** Embed ThemeSwitcher in TopBar — File: `frontend/src/components/layout/TopBar.tsx`
  Replace sun/moon toggle (L50-66) with `<ThemeSwitcher mode="inline" />`.
  → produces: preset switcher accessible on all SPA pages

- **Task 2b:** Remove floating ThemeSwitcher from dashboard — File: `frontend/src/pages/admin/AdminDashboardPage.tsx`
  Remove `<ThemeSwitcher />` import and render. Clean up unused import.
  → produces: no duplicate theme controls

### [parallel] Wave 3: Apply theme-card-overlay to map widgets (needs Task 1a)

- **Task 3a:** Update KpiPills — File: `frontend/src/components/fleet/KpiPills.tsx`
  Replace `bg-slate-800/80 rounded-lg ... border border-slate-700/50` with `.theme-card-overlay` + CSS vars for text

- **Task 3b:** Update MetricPairs — File: `frontend/src/components/metrics/MetricPairs.tsx`
  Replace 3x `bg-slate-800/60 rounded-lg` with `.theme-card-overlay`

- **Task 3c:** Update MapLegendWidget — File: `frontend/src/widgets/MapLegendWidget.tsx`
  Replace `bg-slate-800/60` with `.theme-card-overlay`

- **Task 3d:** Update RouteComparisonWidget — File: `frontend/src/widgets/RouteComparisonWidget.tsx`
  Replace 3x `bg-slate-800/60` with `.theme-card` (solid context, not overlay)

- **Task 3e:** Update FleetSidebar — File: `frontend/src/components/fleet/FleetSidebar.tsx`
  Replace `bg-slate-800/60` and `bg-slate-800/80` with `.theme-card-overlay`

- **Task 3f:** Update RouteList + VehicleList — Files: `frontend/src/components/fleet/RouteList.tsx`, `VehicleList.tsx`
  Replace `bg-slate-800/50` and `bg-slate-800/80` with `.theme-card-overlay` + CSS vars for text

### Wave 4: Verify (needs all waves)

- **Task 4:** TypeScript build + lint + run tests — verify zero errors, all tests pass

## Phase 2: Not needed (v0 is the target)
