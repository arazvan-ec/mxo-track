# Plan: Sidebar Adaptive Glass

**Spec:** `docs/superpowers/specs/2026-04-09-sidebar-adaptive-glass-design.md`
**Branch:** `claude/improve-sidebar-transparency-1dGm0`

## Phase 1 (v0): Working Implementation

### [parallel] Wave 1: Foundation (3 tasks)

**1a: CSS adaptive glass on NavigationSidebar**
- Modify `frontend/src/components/layout/NavigationSidebar.tsx:168`
- Change overlay `<aside>` style: `backgroundColor` → `--color-surface-glass`
- Add `backdropFilter: blur(24px) brightness(0.3) saturate(0.3)` + webkit prefix
- Add subtle border accent `--color-border-accent` for visual separation
→ produces: sidebar with frosted glass effect on all presets

**1b: Create `useAdaptiveOpacity` hook**
- New file: `frontend/src/hooks/useAdaptiveOpacity.ts`
- Accepts: `isOpen: boolean`
- Returns: `{ brightnessValue: number }` (default 0.3, adjusted 0.15-0.3 based on map brightness)
- Logic:
  1. If not open → return default
  2. Query `.maplibregl-canvas` in DOM
  3. If not found → return default (not on map page)
  4. Try: create temp 2D canvas, `drawImage` from WebGL canvas, sample luminance
  5. Catch: return default (canvas not readable)
  6. Map luminance [0-255] to brightness [0.15-0.3] (bright bg → lower brightness = more darkening)
  7. On `moveend`: debounced re-measure (300ms)
  8. Cleanup listeners on unmount
→ produces: hook ready for integration

**1c: Enable canvas reading in MapCanvas**
- Modify `frontend/src/components/maps/MapCanvas.tsx:69`
- Add `preserveDrawingBuffer` prop to `<Map>` component
→ produces: WebGL canvas readable via `drawImage`

### Wave 2: Integration (1 task)

**2: Wire hook into NavigationSidebar**
- Import `useAdaptiveOpacity` in NavigationSidebar
- Call with `!inline` as `isOpen`
- Use returned `brightnessValue` in `backdropFilter` string template
- Overlay mode only — inline mode keeps current solid background
→ produces: sidebar dynamically adjusts darkness based on map content

### Wave 3: Polish + Backdrop (1 task)

**3: Improve backdrop overlay + verify presets**
- In `frontend/src/index.css`: change `.dark` `--color-surface-overlay` from `rgba(0,0,0,0.60)` to `rgba(0,0,0,0.70)`
- Manually verify: default dark, glass dark, command, bento dark, dense dark
- Verify light theme presets work correctly too
→ produces: dimmer backdrop behind sidebar, verified across all presets

## Phase 2 (Mature): N/A — scope is small, v0 is production-ready

## Task Summary

| # | Task | Files | Lines est. |
|---|------|-------|------------|
| 1a | CSS adaptive glass | NavigationSidebar.tsx | ~8 |
| 1b | useAdaptiveOpacity hook | hooks/useAdaptiveOpacity.ts | ~55 |
| 1c | preserveDrawingBuffer | MapCanvas.tsx | ~1 |
| 2 | Wire hook | NavigationSidebar.tsx | ~5 |
| 3 | Backdrop + verify | index.css | ~2 |
| **Total** | | **4 files** | **~71 lines** |
