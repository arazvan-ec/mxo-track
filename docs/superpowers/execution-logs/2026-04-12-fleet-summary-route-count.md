---
type: bugfix
tags: []
files_touched: []
patterns: []
outcome: success
outcome_verified_at: null
regressions_later: []
pr_number: 246
estimated_lines: null
actual_lines: null
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-12 — Fleet Summary Route Count Fix

**Type:** bug fix
**Branch:** `claude/verify-image-routes-5184Z`

## Root Cause

`FleetOverviewService::getFleetSummary()` counted only `RouteStatus::ACTIVE` routes
for the KPI pill, while `getFleetMapData()` fetched both `ACTIVE` and `PLANNED` routes
for map rendering. This caused the bottom bar to show "1 ROUTES" when 5+ routes were
visually drawn on the map.

## Pattern-Wide Search

Checked all route-status queries in `FleetOverviewService.php`:
- `getFleetMapData()` — already correct (`ACTIVE` + `PLANNED`)
- `getFleetSummary()` — **the bug** (only `ACTIVE`)
- `getCustomerKpis()` — customer-scoped, separate context, not affected
- `getActiveRoutesProgress()` — customer-scoped, separate context, not affected

## Fix

Changed `getFleetSummary()` route count query from:
```php
->where('r.status = :status')
->setParameter('status', RouteStatus::ACTIVE)
```
To:
```php
->where('r.status IN (:statuses)')
->setParameter('statuses', [RouteStatus::ACTIVE, RouteStatus::PLANNED])
```

## Verification

- PHP lint: clean
- PHPUnit: 663 tests, 1 pre-existing failure (GitLogReaderTest, unrelated)
- No new failures introduced

## Lessons

- KPI queries and map data queries must use identical status filters when they
  represent the same visual concept. When adding a new status to one query,
  grep for all related queries in the same service to maintain consistency.
