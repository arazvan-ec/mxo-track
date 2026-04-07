# Execution Log — 2026-04-07 — Dark Map Initial Load Fix

**Type:** bug fix
**Branch:** `claude/test-deployment-workflow-0VoSc`

## Root Cause

MapLibre GL does not apply raster paint properties (`raster-brightness-max`, `raster-saturation`, `raster-contrast`) to tiles loaded during map initialization. These properties only take effect after a user interaction (zoom/pan) triggers a tile repaint.

**Evidence:** Screenshots showing light OSM tiles on initial load vs dark-filtered tiles after zoom. Both taken at same timestamp. UI elements (header, bottom panel) correctly dark — confirming theme context resolves correctly; issue is MapLibre-specific.

## Pattern-Wide Search

Only one map canvas component (`MapCanvas.tsx`) — single point of raster tile initialization. No other map views affected beyond this component.

## Fix

Added `onLoad` callback to `MapCanvas.tsx` that re-applies raster paint properties via `setPaintProperty()` inside `requestAnimationFrame()`. This forces MapLibre to repaint tiles with correct dark filter after initial load completes.

**Files changed:** `frontend/src/components/maps/MapCanvas.tsx` (+14 lines)

## Verification

- TypeScript: ✅ clean
- Tests: ✅ 50 passed (9 test files)
- Lint: ✅ clean

## Retrospective

- **What worked:** Direct investigation of MapCanvas + map-style.ts identified the issue quickly. The fix is minimal and targeted.
- **Lesson:** MapLibre raster paint properties have a known limitation on initial tile load. If we switch to vector tiles (Protomaps) in the future, this workaround can be removed since vector tile styling works differently.
