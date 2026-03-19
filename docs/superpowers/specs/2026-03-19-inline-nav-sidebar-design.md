# Design Spec: Inline Navigation Sidebar in DualMenuShell

**Date:** 2026-03-19
**Context:** User feedback — the NavigationSidebar as overlay covers the data sidebar. Both sidebars should coexist side by side (inline).
**Bounded context:** MapView — pragmatic (React SPA UI change only)

## Problem

`DualMenuShell` renders `NavigationSidebar` as a fixed overlay (z-50 + backdrop). When the user opens navigation, it covers the entire screen including the data sidebar. The user wants both sidebars visible simultaneously, side by side.

## Decision

**Approach A: Prop `mode` on NavigationSidebar (responsive)**

Add `mode: 'overlay' | 'inline'` prop to `NavigationSidebar`:
- `inline`: renders as static flex child (`w-64 flex-shrink-0`), no backdrop, no fixed positioning
- `overlay`: current behavior (fixed, z-50, backdrop, slide-in animation)

`DualMenuShell` uses a responsive breakpoint to choose mode:
- Desktop (`>= lg` / 1024px): `mode="inline"` — nav sidebar is first flex child
- Mobile (`< lg`): `mode="overlay"` — nav sidebar overlays as before

### Alternatives Discarded

- **B: Two separate components** — More files/indirection for a simple change. YAGNI.
- **C: CSS-only responsive** — Backdrop needs JS logic anyway (conditional rendering). Mixes responsibilities.

## Layout

### Desktop (>= 1024px)
```
[Nav w-64 (inline)] | [Data w-96 (inline)] | [Map (flex-1)]
```

### Mobile (< 1024px)
```
[Data sidebar] | [Map]
+ Nav overlay (fixed, z-50) when toggled
```

## Behavior

- `navOpen` starts `false` (user clicks hamburger to open)
- `dataOpen` starts `true` (existing behavior)
- Both toggles are independent
- Nav hamburger button stays in the same position (top-left of map area)

## Files to Modify

1. `frontend/src/components/layout/NavigationSidebar.tsx` — Add `mode` prop, conditional rendering
2. `frontend/src/components/layout/DualMenuShell.tsx` — Add `useIsDesktop()` hook, render nav inline on desktop

## New File

3. `frontend/src/hooks/useIsDesktop.ts` — Simple `matchMedia` hook for `(min-width: 1024px)` breakpoint
