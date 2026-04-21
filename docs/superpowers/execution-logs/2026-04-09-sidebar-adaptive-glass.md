---
type: feature
tags: [glass-overlay, sidebar]
files_touched: [frontend/src/components/layout/NavigationSidebar.tsx, frontend/src/components/maps/MapCanvas.tsx, frontend/src/hooks/useAdaptiveOpacity.ts, frontend/src/index.css]
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

# Execution Log — 2026-04-09 — Sidebar Adaptive Glass

**Type:** feature
**Branch:** `claude/improve-sidebar-transparency-1dGm0`

## Brainstorming

**Alternatives considered:**
- **A: Match TopBar pattern** — `--color-surface-glass` + `blur(16px)`. Simple but same opacity for sidebar (dense text) and TopBar (one line). Rejected: insufficient for sidebar readability.
- **B: Glass reforzado** — `blur(24px)` + border accent + darker backdrop. Better readability but purely static. Rejected: user wanted dynamic adaptation.
- **C: Dedicated `--color-sidebar-bg` per preset** — Fine-grained control. Rejected: over-engineering, new variable in 5 presets.
- **D: CSS adaptive glass** — `backdrop-filter: blur + brightness + saturate`. CSS processes real pixels behind element at GPU speed. Covers 90% of cases. Selected as base.
- **E: Canvas sampling JS** — Read MapLibre canvas pixels, adjust opacity. Full dynamic but requires `preserveDrawingBuffer` + complex hook. Rejected standalone: too heavy.
- **F: Hybrid (D+E lightweight)** — CSS base + JS only for extreme brightness detection. **Selected.**

**Chosen approach:** F — CSS `backdrop-filter` with `brightness()` filter for GPU-level dynamic adaptation + JS hook that samples MapLibre canvas luminance to fine-tune brightness value for bright map areas.

## Planning

- 5 tasks, 3 waves (Wave 1 parallel: 3 tasks, Wave 2: integration, Wave 3: polish)
- Estimated: ~71 lines across 4 files
- Actual: ~85 lines (hook slightly larger due to cleanup logic)

## Implementation

**Files changed:**
- `frontend/src/components/layout/NavigationSidebar.tsx` — Overlay mode: `--color-surface-glass` + dynamic `backdrop-filter`
- `frontend/src/components/maps/MapCanvas.tsx` — Added `preserveDrawingBuffer`
- `frontend/src/hooks/useAdaptiveOpacity.ts` — New hook: canvas luminance sampling
- `frontend/src/index.css` — Dark backdrop overlay 0.60 → 0.70

**Key decisions during implementation:**
- Used `MutationObserver` on `.maplibregl-map` container instead of direct map event binding (sidebar has no access to map instance)
- Debounced re-measurement at 300ms to avoid excessive canvas reads
- Luminance→brightness mapping: linear from 0.30 (dark bg) to 0.15 (bright bg)
- `try/catch` around canvas read for graceful fallback when `preserveDrawingBuffer` unavailable

## Verification

- TypeScript: ✅ (0 errors)
- Vite build: ✅ (6.01s)
- PHPUnit: skipped (frontend-only changes, no PHP modified)

## Lessons

- `backdrop-filter: brightness()` is inherently dynamic — it processes real pixels behind the element per frame. This is often sufficient without JS canvas sampling.
- `preserveDrawingBuffer: true` on MapLibre has minimal visible performance cost but enables canvas reading for features like screenshots and adaptive UI.
- The sidebar needs stronger blur (24px) than TopBar (16px) because it contains dense text — multiple menu items vs a single toolbar line.
