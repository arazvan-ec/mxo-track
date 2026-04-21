---
type: bugfix
tags: []
files_touched: []
patterns: []
outcome: null
outcome_verified_at: null
regressions_later: []
pr_number: null
estimated_lines: null
actual_lines: null
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-06 — Fix Map Tile Loading

**Type:** bug fix
**Branch:** `claude/fix-map-loading-issue-DsP5i`

## Root Cause

MapLibre skips rendering remote vector tiles (Protomaps) on initial paint while local GeoJSON layers (polylines, markers) render fine. The issue is NOT container dimensions (resize() confirmed no-op). The tile rendering pipeline needs an interaction event to activate.

## Attempts

1. **map.resize() in onLoad** — no effect, dimensions already correct
2. **ResizeObserver on container** — no effect, container doesn't resize
3. **requestAnimationFrame + resize()** — no effect, same reason
4. **panBy micro-nudge (1px pan + undo)** — triggers `_sourcesDirty = true` in MapLibre, same code path as user interaction

## Changes

- `MapCanvas.tsx`: Added 1px panBy workaround in onMapLoad, removed unnecessary ResizeObserver wrapper
- `CLAUDE.md`: Added fix invalidation rule — reset debug state when user reports fix didn't work
- Added 49 frontend tests (Vitest + Testing Library) for map components

## Lessons

- When MapLibre renders some layers (GeoJSON) but not others (remote tiles), the issue is in the tile fetch/render pipeline, not canvas sizing
- `map.resize()` is a no-op when dimensions are already correct — don't assume it's the fix for all "blank map" issues
- Always verify the fix is deployed before claiming success
