# Spec — Innovative Dashboard Design

**Date:** 2026-04-07
**Type:** Feature (UI enhancement)
**Branch:** `claude/innovative-dashboard-design-ddekk`
**Approach:** A — Theme Switcher + 4 Visual Presets + New Visualizations

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| `ThemeProvider` (light/dark/system) | **Transform** | Extend with visual preset system |
| `index.css` (CSS variables) | **Transform** | Add 4 preset variable sets |
| `AdminDashboardPage.tsx` | **Transform** | New layout with Bento grid, greeting header, animations |
| `DashboardKpisWidget.tsx` | **Transform** | Add animated counters, sparkline SVG, staggered entrance |
| `SystemHealthWidget.tsx` | **Transform** | Add radial gauge for latency, pulse indicators |
| `InfrastructureMetricsWidget.tsx` | **Transform** | Add animated progress bars, better visual hierarchy |
| `MiniReportsWidget.tsx` | **Transform** | Animated bar chart, improved driver list |
| `ActivityFeedWidget.tsx` | **Transform** | Better feed styling with theme-aware cards |
| `CollapsibleWidget.tsx` | **Transform** | Theme-aware styling via CSS variables |
| Widget registry | **Include** | No structural changes |
| `useAdminDashboard.ts` hook | **Include** | No changes |
| `useMe` hook | **Include** | Used for greeting header |
| Backend APIs | **Include** | No changes needed |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| Operator/Customer dashboards | **Omit** | Different scope; themes will apply globally via CSS vars |
| Backend controllers | **Omit** | Frontend-only change |
| Widget entity model (DB) | **Omit** | No new widget types, only visual enhancement |

## Design

### 1. Theme Preset System

Extend `ThemeProvider` to support a `preset` dimension orthogonal to `mode` (light/dark):

```typescript
type ThemePreset = 'default' | 'glass' | 'command' | 'bento' | 'dense';
```

Each preset defines CSS variables that override the base light/dark tokens:

- **Default** — Current design (no overrides)
- **Glass** — `backdrop-filter: blur`, translucent surfaces, subtle borders
- **Command Center** — Deep navy/black base, cyan/green neon accents, mono font for data
- **Bento** — Large border-radius, variable card sizes, playful shadows, warm palette
- **Data Dense** — Compact spacing, monospace numbers, muted colors, max info density

### 2. CSS Architecture

Add preset classes (`.preset-glass`, `.preset-command`, etc.) on `<html>` alongside `.dark`:

```css
.preset-glass {
  --color-surface-elevated: rgba(255, 255, 255, 0.60);
  --card-blur: blur(16px);
  --card-border: 1px solid rgba(255, 255, 255, 0.18);
  --card-radius: 1rem;
  --card-shadow: 0 8px 32px rgba(0,0,0,0.08);
}
.preset-glass.dark {
  --color-surface-elevated: rgba(15, 23, 42, 0.60);
  --card-border: 1px solid rgba(255, 255, 255, 0.08);
}
/* ... similar for command, bento, dense */
```

### 3. New Visualization Components

#### AnimatedCounter
- Count-up animation from 0 to target value over 600ms
- Uses `requestAnimationFrame` with easing
- Triggers on mount and on value change

#### SparklineSVG
- Tiny inline SVG (80×24) showing 7-day trend
- Pure SVG path, no dependencies
- Derived from `daily_deliveries` data

#### RadialGauge
- SVG circle with stroke-dasharray for latency visualization
- Green → amber → red based on threshold
- Shows ms value in center

#### AnimatedBarChart
- Bars grow from 0 height with CSS transition on mount
- Staggered delay per bar (50ms each)

#### PulseIndicator
- CSS animation ping for live services
- Static dot for offline services

#### StaggeredEntrance
- Wrapper component that adds `opacity-0 → opacity-100` + `translateY(8px) → 0`
- Each child delayed by `index * 60ms`
- Uses CSS transitions (no JS animation library)

### 4. Dashboard Layout Changes

#### Greeting Header
```
Buenos días, {firstName}
Lunes, 7 de abril de 2026 · Última actualización hace 45s
```

#### Theme Switcher Button
Floating button (bottom-right) with palette icon. Click opens popover with 5 presets as visual thumbnails + light/dark toggle.

### 5. Files Changed

| File | Change |
|------|--------|
| `context/ThemeProvider.tsx` | Add `preset` state, apply class on `<html>` |
| `index.css` | Add 4 preset variable blocks (~80 lines each) |
| `pages/admin/AdminDashboardPage.tsx` | Complete rewrite with new layout |
| `widgets/DashboardKpisWidget.tsx` | Add AnimatedCounter + SparklineSVG |
| `widgets/SystemHealthWidget.tsx` | Add RadialGauge + PulseIndicator |
| `widgets/InfrastructureMetricsWidget.tsx` | Theme-aware + animated progress |
| `widgets/MiniReportsWidget.tsx` | AnimatedBarChart + improved styling |
| `widgets/ActivityFeedWidget.tsx` | Theme-aware card styling |
| `components/widgets/CollapsibleWidget.tsx` | Use CSS vars instead of hardcoded colors |
| NEW `components/ui/AnimatedCounter.tsx` | Reusable count-up component |
| NEW `components/ui/SparklineSVG.tsx` | Reusable sparkline component |
| NEW `components/ui/RadialGauge.tsx` | Reusable gauge component |
| NEW `components/ui/StaggeredEntrance.tsx` | Reusable entrance animation |
| NEW `components/ui/ThemeSwitcher.tsx` | Floating preset picker |
