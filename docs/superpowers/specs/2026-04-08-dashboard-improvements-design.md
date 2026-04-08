# Spec — Dashboard Improvements (Post-Retrospective)

**Date:** 2026-04-08
**Type:** Feature (3 improvements)
**Branch:** `claude/innovative-dashboard-design-ddekk`
**Approach:** Scalability-first — TopBar integration, colocated tests, dual card variants

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| `TopBar.tsx` (theme toggle L50-66) | **Transform** | Replace simple toggle with preset dropdown |
| `ThemeSwitcher.tsx` (floating) | **Transform** | Adapt to inline/embedded mode for TopBar |
| `AdminDashboardPage.tsx` (renders `<ThemeSwitcher />`) | **Transform** | Remove floating ThemeSwitcher |
| `KpiPills.tsx` (L27: `bg-slate-800/80`) | **Transform** | → `.theme-card-overlay` |
| `MetricPairs.tsx` (L26,37,52: `bg-slate-800/60`) | **Transform** | → `.theme-card-overlay` |
| `RouteComparisonWidget.tsx` (L36,74,130) | **Transform** | → `.theme-card` |
| `MapLegendWidget.tsx` (L35) | **Transform** | → `.theme-card-overlay` |
| `FleetSidebar.tsx` (L67,111) | **Transform** | → `.theme-card-overlay` |
| `RouteList.tsx` (L27) | **Transform** | → `.theme-card-overlay` |
| `VehicleList.tsx` (L35) | **Transform** | → `.theme-card-overlay` |
| Test setup (vitest + happy-dom) | **Include** | Already configured |
| StopPopup.test.tsx | **Include** | Pattern to follow |
| `index.css` | **Transform** | Add `.theme-card-overlay` class |
| `LanguageSwitcher.tsx` | **Include** | Dropdown pattern reference |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| Customer Twig dashboard | **Omit** | Not React SPA, uses Alpine.js — out of scope |
| Backend APIs | **Omit** | Frontend-only changes |
| Widget entity model (DB) | **Omit** | No new widget types |

## Design

### Improvement 1: ThemeSwitcher in TopBar

**Problem:** ThemeSwitcher only visible on AdminDashboardPage. Pattern doesn't scale — future settings controls would need their own floating buttons.

**Approach:** Embed preset dropdown in TopBar, following LanguageSwitcher dropdown pattern.

- Modify `ThemeSwitcher.tsx` to support two modes: `inline` (for TopBar) and `floating` (legacy, removable)
- Replace TopBar's sun/moon toggle (L50-66) with a compact button that opens preset dropdown
- Remove `<ThemeSwitcher />` from `AdminDashboardPage.tsx`
- Scalability: establishes "settings controls live in TopBar" pattern

### Improvement 2: UI Component Tests

**Problem:** 4 new UI components lack tests. Risk of regression when modifying for new presets.

**Approach:** Colocated test files following StopPopup.test.tsx pattern.

- `AnimatedCounter.test.tsx` — mock RAF with `vi.useFakeTimers()`, verify reaches target value
- `SparklineSVG.test.tsx` — verify SVG path generation, dot on last point, min data handling
- `RadialGauge.test.tsx` — verify color thresholds (green/amber/red), center text rendering
- `StaggeredEntrance.test.tsx` — verify incremental `animationDelay` on children

### Improvement 3: `.theme-card-overlay` for Map Widgets

**Problem:** Widgets over maps need transparency+blur always, regardless of preset. Plain `.theme-card` would make Bento preset (opaque cards) block map visibility.

**Approach:** Two card variant classes, both reading preset CSS vars.

```css
.theme-card-overlay {
  /* Inherits preset colors but forces translucency + blur */
  background: var(--color-surface-glass);
  backdrop-filter: blur(12px);
  border: 1px solid var(--color-border-subtle);
  border-radius: var(--card-radius);
  box-shadow: var(--card-shadow);
  transition: box-shadow 0.2s, background 0.3s;
}
```

**Files to update:** KpiPills, MetricPairs, MapLegendWidget, FleetSidebar, RouteList, VehicleList (map context) + RouteComparisonWidget (solid context → `.theme-card`)
