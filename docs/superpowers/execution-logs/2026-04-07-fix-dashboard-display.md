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

# Execution Log — 2026-04-07 — Fix Dashboard Display

**Type:** bug fix
**Branch:** `claude/fix-dashboard-display-qmdu8`

## Root Cause

3 TypeScript errors prevented the frontend from building (`tsc -b` fails before `vite build`):

1. `dashboard-widget.tsx:25` — `mercurePublicUrl` prop passed to `AdminDashboardPage` which no longer accepts it (TS2322)
2. `AdminDashboardPage.tsx:6` — unused `DashboardMetrics` import (TS6196)
3. `AdminDashboardPage.tsx:39` — `ringColor` is not a valid React CSS property (TS2353)

These were introduced in commit `6d92596` (Phase 1 — admin dashboard migrated to React SPA). The AdminDashboardPage was rewritten without updating the dashboard-widget entry point that passes props to it.

Additionally, the AdminDashboardPage root div used `flex-1` for height, but its parent in AppLayout is not a flex container — causing the scrollable area to not constrain properly.

## Pattern-Wide Search

Searched for similar issues across the codebase:
- Only 3 TS errors found, all in the same two files
- No other widget entry points pass stale props

## Fix

- Removed stale `mercurePublicUrl` prop and Mercure URL reading from `dashboard-widget.tsx`
- Removed unused `DashboardMetrics` import from `AdminDashboardPage.tsx`
- Removed invalid `ringColor` CSS property
- Changed `flex-1` to `h-full` on dashboard root div for proper scroll behavior

## Verification

- TypeScript: 0 errors
- Frontend tests: 50 passed (9 suites)
- Vite build: success (all assets generated)
- Backend tests: pre-existing failures unrelated to this change

## Lesson

When rewriting a component's interface (removing props), always update ALL entry points that instantiate it — not just the router. The `dashboard-widget.tsx` standalone entry was missed because it's a separate Vite entry point not visible from the router.
