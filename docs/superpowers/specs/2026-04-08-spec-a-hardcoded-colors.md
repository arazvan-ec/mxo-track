# Spec A — Migrate Hardcoded Colors to CSS Variables

**Date:** 2026-04-08
**Type:** Refactor (theme consistency)
**Branch:** `claude/innovative-dashboard-design-ddekk`
**Approach:** Priority-based migration: fleet components first, then admin pages, then layout

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| FleetSidebar.tsx (~10 slate classes) | **Transform** | tabs, search, sidebar bg → CSS vars |
| RouteList.tsx (~8 slate classes) | **Transform** | text colors → CSS vars |
| VehicleList.tsx (~8 slate classes) | **Transform** | text colors, offline indicator → CSS vars |
| RouteProgressBar.tsx (~4 slate classes) | **Transform** | border, text, progress bg → CSS vars |
| VehiclePopup.tsx (~5 slate classes) | **Transform** | popup text → CSS vars |
| StopPopup.tsx (~3 slate classes) | **Transform** | address, recipient text → CSS vars |
| HeaderBar.tsx (~1 slate class) | **Transform** | time display → CSS var |
| ExceptionMapPage.tsx (~15 slate classes) | **Transform** | form inputs, cards, toggles → CSS vars |
| TestRoutingPage.tsx (~5 slate classes) | **Transform** | map overlay buttons → CSS vars |
| RoutePlannerPage.tsx (~20 slate classes) | **Transform** | forms, checkboxes, lists → CSS vars |
| PageLayoutEditorPage.tsx (~20 slate classes) | **Transform** | entire editor page → CSS vars |
| ExceptionLayer.tsx (~5 slate classes) | **Transform** | popup text → CSS vars |
| RouteMapLayers.tsx (~4 slate classes) | **Transform** | toggle button states → CSS vars |
| StopActionPanel.tsx (~3 slate classes) | **Transform** | button colors → CSS vars |
| VehicleActionPanel.tsx (~2 slate classes) | **Transform** | close button → CSS vars |
| UserDropdown.tsx (~6 gray classes) | **Transform** | dropdown styling → CSS vars |
| SearchBar.tsx (~8 gray classes) | **Transform** | input, icon → CSS vars |
| LanguageSwitcher.tsx (~3 gray classes) | **Transform** | dropdown items → CSS vars |
| NotificationBell.tsx (~2 gray classes) | **Transform** | icon → CSS vars |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| Backend templates | **Omit** | Twig uses its own Tailwind config |
| Widget components already migrated | **Omit** | DashboardKpis, SystemHealth, etc. done |

## Design

### Approach: Systematic Class Replacement

For each file, replace hardcoded Tailwind color classes with inline styles referencing CSS variables:

**Map-context components** (FleetSidebar, RouteList, VehicleList, popups):
- `text-slate-200` → `style={{ color: 'var(--color-text-primary)' }}`
- `text-slate-400` → `style={{ color: 'var(--color-text-secondary)' }}`
- `text-slate-500` → `style={{ color: 'var(--color-text-muted)' }}`
- `text-slate-600` → `style={{ color: 'var(--color-text-muted)' }}`
- `bg-slate-800/*` → `.theme-card-overlay` or `var(--color-surface-glass)`
- `border-slate-700/*` → `var(--color-border)` or `var(--color-border-subtle)`

**Solid-context components** (admin pages, layout):
- Same text color mapping
- `bg-slate-800/*` → `var(--color-surface-elevated)` or `.theme-card`
- Form inputs: `var(--color-surface-elevated)` bg + `var(--color-border)` border

### Waves

- **Wave 1 (fleet, ~7 files):** FleetSidebar, RouteList, VehicleList, RouteProgressBar, VehiclePopup, StopPopup, HeaderBar
- **Wave 2 (admin pages, ~5 files):** ExceptionMapPage, TestRoutingPage, RoutePlannerPage, PageLayoutEditorPage, ExceptionLayer, RouteMapLayers
- **Wave 3 (panels + layout, ~6 files):** StopActionPanel, VehicleActionPanel, UserDropdown, SearchBar, LanguageSwitcher, NotificationBell

**Estimated:** ~200 class replacements, 19 files, ~600 lines changed
