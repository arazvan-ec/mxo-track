# Plan — Innovative Dashboard Design

**Spec:** `docs/superpowers/specs/2026-04-07-innovative-dashboard-design.md`
**Branch:** `claude/innovative-dashboard-design-ddekk`

## Phase 1 (v0): Working implementation

### [parallel] Wave 1: Infrastructure

- **Task 1a:** Extend ThemeProvider — File: `frontend/src/context/ThemeProvider.tsx`
  Add `preset` state (default|glass|command|bento|dense), apply `.preset-{name}` class on `<html>`, persist in localStorage
  → produces: `useTheme().preset`, `useTheme().setPreset()`

- **Task 1b:** Add 4 preset CSS variable blocks — File: `frontend/src/index.css`
  Add `.preset-glass`, `.preset-command`, `.preset-bento`, `.preset-dense` with light+dark variants
  → produces: CSS variables for all 4 visual presets

- **Task 1c:** Create reusable UI components — Files: `frontend/src/components/ui/`
  - `AnimatedCounter.tsx` — count-up animation via requestAnimationFrame
  - `SparklineSVG.tsx` — tiny 80×24 inline SVG trend line
  - `RadialGauge.tsx` — SVG circle gauge for latency with color thresholds
  - `StaggeredEntrance.tsx` — wrapper with fade+slide per child, 60ms stagger
  → produces: 4 new reusable components

### Wave 2: Theme Switcher (needs Task 1a)

- **Task 2:** Create ThemeSwitcher component — File: `frontend/src/components/ui/ThemeSwitcher.tsx`
  Floating button bottom-right, popover with 5 preset thumbnails + light/dark toggle
  → produces: ThemeSwitcher ready for dashboard

### [parallel] Wave 3: Widget Upgrades (needs Task 1b + 1c)

- **Task 3a:** Upgrade DashboardKpisWidget — File: `frontend/src/widgets/DashboardKpisWidget.tsx`
  Add AnimatedCounter for values, SparklineSVG for trends, theme-aware via CSS vars

- **Task 3b:** Upgrade SystemHealthWidget — File: `frontend/src/widgets/SystemHealthWidget.tsx`
  Add RadialGauge for latency, PulseIndicator for live status, theme-aware

- **Task 3c:** Upgrade InfrastructureMetricsWidget — File: `frontend/src/widgets/InfrastructureMetricsWidget.tsx`
  Animated progress bars, better visual hierarchy, theme-aware

- **Task 3d:** Upgrade MiniReportsWidget — File: `frontend/src/widgets/MiniReportsWidget.tsx`
  Animated bar chart with staggered growth, improved driver list, theme-aware

- **Task 3e:** Upgrade ActivityFeedWidget — File: `frontend/src/widgets/ActivityFeedWidget.tsx`
  Theme-aware card styling via CSS vars

- **Task 3f:** Upgrade CollapsibleWidget — File: `frontend/src/components/widgets/CollapsibleWidget.tsx`
  Replace hardcoded colors with CSS vars for theme compatibility

### Wave 4: Dashboard Page (needs Wave 2 + Wave 3)

- **Task 4:** Rewrite AdminDashboardPage — File: `frontend/src/pages/admin/AdminDashboardPage.tsx`
  Greeting header, Bento grid layout, ThemeSwitcher integration, StaggeredEntrance for all sections
  → produces: complete redesigned dashboard page

### Wave 5: Verify

- **Task 5:** TypeScript build + lint — verify zero errors

## Phase 2: Not needed (v0 is the target)
