# Plan — Repository & Performance Improvements

**Spec:** `docs/superpowers/specs/2026-04-07-repo-improvements-design.md`
**Branch:** `claude/analyze-repo-improvements-vqgSV`

## Phase 1 (v0)

### [parallel] Tarea 1a + 1b

- **1a:** Fix N+1 in AlertService — Replace loop with DQL LEFT JOIN query
  - File: `backend/src/Service/AlertService.php`
  - TDD: write test for getOfflineVehicles() → verify fail → implement JOIN → verify pass → commit

- **1b:** Narrow catch in 10 standard repositories
  - Files: `backend/src/Repository/{Route,Vehicle,Shipment,RouteStop,Notification,Pod,RouteOptimizationLog,User,PageLayout,WidgetDefinition}Repository.php`
  - Change: `catch (\Throwable)` → `catch (\InvalidArgumentException)`
  - Commit after all 10

### Tarea 2 (independent of 1a/1b)

- Narrow catch in 4 Infrastructure repositories
  - Files: `backend/src/Infrastructure/Route/Doctrine/Doctrine{Route,RouteStop}Repository.php`, `backend/src/Infrastructure/Shipment/Doctrine/Doctrine{Pod,Shipment}Repository.php`
  - Same mechanical change
  - Commit

### Tarea 3 (after all)

- Run full test suite: `php vendor/bin/phpunit`
- Run lint: `make lint`
- Verify 0 regressions

## Phase 2 (Mature)

Not applicable — changes are mechanical, no refactoring needed.
