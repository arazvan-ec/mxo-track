---
type: feature
tags: [card, route]
files_touched: [docs/superpowers/plans/2026-04-14-expand-routes-card.md, docs/superpowers/specs/2026-04-14-expand-routes-card-design.md, frontend/src/api/hooks/useActiveRoutes.ts, frontend/src/widgets/DashboardKpisWidget.tsx]
patterns: []
outcome: success
outcome_verified_at: null
regressions_later: []
pr_number: 253
estimated_lines: null
actual_lines: null
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-14 — Expandable Routes Card

**Type:** feature
**Branch:** `claude/expand-routes-card-ojzGb`
**Spec:** `docs/superpowers/specs/2026-04-14-expand-routes-card-design.md`
**Plan:** `docs/superpowers/plans/2026-04-14-expand-routes-card.md`

## Summary
Added expandable "Rutas activas" KPI card on the admin dashboard. Click to expand shows up to 5 active routes with driver, vehicle, and stops progress bar. Uses lazy-loading from existing `/api/admin/routes?status=ACTIVE&limit=5` endpoint (Enfoque B).

## Changes
| File | Change |
|------|--------|
| `frontend/src/api/hooks/useActiveRoutes.ts` | New hook — fetches active routes with `enabled` flag |
| `frontend/src/widgets/DashboardKpisWidget.tsx` | New `ExpandableRouteCard` component replacing the simple `KpiCard` for routes |

## Decisions
- **Enfoque B chosen** (lazy-load) over Enfoque A (embed in dashboard payload) — avoids backend changes, reuses existing endpoint
- **5 route limit** — sufficient for dashboard preview without overwhelming the card
- **No new widget type** — kept as enhancement to `dashboard_kpis` widget

## Verification
- `npm run build`: ✅ (tsc -b + vite build)
- Backend lint: ✅ (no backend changes needed)

## Retrospective
1. **Estimate accuracy:** Estimated ~2 files, ~80 lines. Actual: 2 code files, +100 lines. Close — the ExpandableRouteCard component was slightly larger than expected due to complete UX coverage (loading, empty, progress bar, expand/collapse animation).
2. **Process gap:** Initially set `flow_type = "code_change"` instead of `"full"` — the write hook correctly blocked the edit. Lesson: always use the valid flow_type enum values (`full` for code changes).
3. **Emergent patterns:** First KPI card with inline expansion + lazy-load. If this pattern applies to other KPI cards (paradas pendientes, imports, posiciones), extract a reusable `ExpandableKpiCard` component. Currently 1 occurrence — not yet a pattern to extract.
