# Spec: Glassmorphism Theme System + Visual Redesign

**Date:** 2026-03-24
**Type:** Enhancement (visual redesign)
**Branch:** `claude/fix-map-centering-sheet-nbZRd`

---

## Problem

The current UI has hardcoded dark-only colors scattered across 15+ components. The BottomSheet uses opaque bg-slate-900 (no transparency), TopBar is inconsistently light (bg-white), and there's no theme system. The user wants a CodexBar-inspired glassmorphism design with dark/light mode toggle.

## Solution: Tailwind v4 Design Tokens + Glassmorphism

### Design Language (CodexBar-inspired)

**Glassmorphism:**
- BottomSheet: `backdrop-blur-xl` + semi-transparent background (~80% opacity)
- Map visible through the sheet with frosted glass effect
- Subtle 1px border with accent tint

**Color Accent:** Teal (replacing blue)
- Dark: teal-400 (#2dd4bf) for text accents, teal-500 (#14b8a6) for interactive
- Light: teal-600 (#0d9488) for text accents, teal-500 for interactive

**Cards/Panels:** Elevated surfaces with subtle borders, rounded-xl corners

### Design Tokens (CSS Variables)

Defined in `index.css` using Tailwind v4 `@theme` syntax + `:root`/`.dark` overrides.

#### Token Catalog

| Token | Purpose | Light | Dark |
|-------|---------|-------|------|
| `--color-surface` | Page/app bg | slate-50 `#f8fafc` | slate-950 `#020617` |
| `--color-surface-elevated` | Cards, panels | white `#ffffff` | slate-900 `#0f172a` |
| `--color-surface-glass` | Glassmorphism bg | white/80% `rgba(255,255,255,0.8)` | slate-900/80% `rgba(15,23,42,0.8)` |
| `--color-surface-overlay` | Sidebar overlay | black/50% | black/50% |
| `--color-text-primary` | Main text | slate-900 `#0f172a` | slate-100 `#f1f5f9` |
| `--color-text-secondary` | Secondary text | slate-500 `#64748b` | slate-400 `#94a3b8` |
| `--color-text-muted` | Subtle text | slate-400 `#94a3b8` | slate-500 `#64748b` |
| `--color-border` | Default borders | slate-200 `#e2e8f0` | slate-700 `#334155` |
| `--color-border-subtle` | Subtle borders | slate-200/50% | slate-700/40% |
| `--color-border-accent` | Accent borders | teal-300/30% | teal-500/30% |
| `--color-accent` | Interactive accent | teal-600 `#0d9488` | teal-400 `#2dd4bf` |
| `--color-accent-bg` | Accent background | teal-500 `#14b8a6` | teal-500 `#14b8a6` |
| `--color-accent-hover` | Accent hover state | teal-700 `#0f766e` | teal-300 `#5eead4` |
| `--color-success` | Delivered/active | emerald-500 `#10b981` | emerald-400 `#34d399` |
| `--color-error` | Exception/error | red-500 `#ef4444` | red-400 `#f87171` |
| `--color-warning` | Warning states | amber-500 `#f59e0b` | amber-400 `#fbbf24` |

#### Map-Specific Tokens (for Protomaps Flavor)

| Token | Purpose | Light | Dark |
|-------|---------|-------|------|
| `--map-background` | Map bg | `#e8ecf0` | `#0f172a` |
| `--map-water` | Water bodies | `#bfdbfe` | `#1e293b` |
| `--map-earth` | Land fill | `#f1f5f9` | `#0f172a` |
| `--map-highway` | Major roads | `#94a3b8` | `#475569` |
| `--map-major` | Secondary roads | `#cbd5e1` | `#334155` |
| `--map-minor` | Minor roads | `#e2e8f0` | `#1e293b` |
| `--map-buildings` | Buildings | `#dde3ea` | `#1a2332` |
| `--map-park` | Parks/green | `#bbf7d0` | `#0d1f1a` |

### ThemeProvider

React context + hook. Persists preference in `localStorage`. Respects system preference on first visit.

```tsx
type Theme = 'light' | 'dark' | 'system';
const ThemeContext = createContext<{ theme: Theme; resolved: 'light' | 'dark'; toggle: () => void }>();
```

Provider adds/removes `dark` class on `<html>` element. MapCanvas listens to theme changes and re-applies Protomaps flavor.

### Component Migration

Each component migrates from hardcoded slate-* classes to semantic token classes.

| Component | Key Changes |
|-----------|-------------|
| **BottomSheet** | `bg-slate-900` → `bg-[var(--color-surface-glass)] backdrop-blur-xl`, `border-slate-700` → `border-[var(--color-border-accent)]`, add `shadow-[0_-8px_32px_rgba(0,0,0,0.15)]` |
| **TopBar** | `bg-white` → `bg-[var(--color-surface-glass)] backdrop-blur-lg`, `border-gray-200` → `border-[var(--color-border)]`, `text-gray-500` → `text-[var(--color-text-secondary)]`, add theme toggle button |
| **NavigationSidebar** | `bg-slate-800/900` → `bg-[var(--color-surface-elevated)]`, `text-white` → `text-[var(--color-text-primary)]` |
| **StopListPanel** | `bg-slate-800/50` → `bg-[var(--color-surface-elevated)]/50`, `text-slate-200` → `text-[var(--color-text-primary)]`, selected: `bg-blue-600/20` → accent-based |
| **RouteSummaryBar** | `text-slate-400` → `text-[var(--color-text-secondary)]`, `text-blue-400` → `text-[var(--color-accent)]` |
| **RouteMetricsPanel** | `bg-slate-800/60` → `bg-[var(--color-surface-elevated)]/60`, accent colors |
| **VehicleInfoPanel** | Same pattern as RouteMetricsPanel |
| **MapCanvas** | `createDarkStyle()` → `createMapStyle(theme)` switching Protomaps flavor |
| **index.css popups** | Hardcoded rgba → CSS variable-based |
| **colors.ts** | ROUTE_COLORS stay (brand colors), STOP_STATUS_COLORS updated to use success/error/warning tokens where possible |

### MapLibre Theme Switching

`dark-style.ts` → `map-style.ts` with two flavors:

```ts
export function createMapStyle(theme: 'light' | 'dark'): StyleSpecification {
  const flavor: Flavor = theme === 'dark' ? darkFlavor : lightFlavor;
  // ... build style
}
```

MapCanvas subscribes to theme context and calls `map.setStyle()` on change.

### Theme Toggle Button

In TopBar, a sun/moon icon button:
- Dark mode: sun icon (click to switch to light)
- Light mode: moon icon (click to switch to dark)
- 24x24 icon, `text-[var(--color-text-secondary)]` with hover accent

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| BottomSheet structure/drag | Maintain | Only visual classes change |
| TopBar layout | Maintain | Only colors + add toggle |
| NavigationSidebar layout | Maintain | Only colors |
| StopListPanel structure | Maintain | Only colors |
| RouteSummaryBar structure | Maintain | Only colors |
| RouteMetricsPanel structure | Maintain | Only colors |
| VehicleInfoPanel structure | Maintain | Only colors |
| MapCanvas component logic | Maintain | Add theme subscription |
| dark-style.ts | Transform → map-style.ts | Support both flavors |
| index.css MapLibre overrides | Transform | Use CSS variables |
| colors.ts ROUTE_COLORS | Maintain | Brand/route colors unchanged |
| colors.ts STOP_STATUS_COLORS | Transform | Use semantic tokens |
| SKILL_COLORS | Maintain | Specific semantic meaning |
| Adaptive content zones (just added) | Maintain | Independent of visual theme |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| Twig templates theming | Omit | React SPA only; Twig pages are legacy |
| sidebar-widget / topbar-widget | Omit | Embedded widgets in Twig, separate scope |
| System preference auto-detect | Include | Free with matchMedia, good UX |
| Animated transition between themes | Omit | Complexity for minimal gain; instant switch is fine |
| Per-component dark: prefix | Omit | Using CSS variables instead — single source of truth |

## Files Affected

**New files:**
- `frontend/src/context/ThemeProvider.tsx` — theme context + provider + hook
- `frontend/src/components/maps/styles/light-style.ts` — light Protomaps flavor

**Modified files:**
- `frontend/src/index.css` — design tokens, popup theme vars
- `frontend/src/main.tsx` — wrap with ThemeProvider
- `frontend/src/components/bottom-sheet/BottomSheet.tsx` — glassmorphism classes
- `frontend/src/components/layout/TopBar.tsx` — themed + toggle button
- `frontend/src/components/layout/NavigationSidebar.tsx` — themed
- `frontend/src/components/panels/StopListPanel.tsx` — themed
- `frontend/src/components/panels/RouteSummaryBar.tsx` — themed
- `frontend/src/components/panels/RouteMetricsPanel.tsx` — themed
- `frontend/src/components/panels/VehicleInfoPanel.tsx` — themed
- `frontend/src/components/maps/MapCanvas.tsx` — theme-reactive style
- `frontend/src/components/maps/styles/dark-style.ts` — refactor to shared util
- `frontend/src/components/maps/shared/colors.ts` — accent color update
