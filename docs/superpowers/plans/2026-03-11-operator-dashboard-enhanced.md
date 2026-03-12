# Plan: Feature 1.4 — Operator Dashboard Enhanced

## Tasks

### Task 1: Create OperatorKpiService with tests (TDD)

**Test file:** `backend/tests/Unit/Service/OperatorKpiServiceTest.php`

Tests:
1. `testCollectKpisReturnsExpectedKeys` — all 7 keys present
2. `testSuccessRate7dCalculation` — delivered/(delivered+exceptions) * 100
3. `testTopDriversLimitedToThree` — max 3 drivers returned
4. `testVehiclesWithPositionCount` — counts vehicles with last position

**Implementation file:** `backend/src/Service/OperatorKpiService.php`

### Task 2: Add KPI JSON endpoint

**File:** `backend/src/Controller/Operator/OperatorDashboardController.php`

- Add `#[Route('/dashboard/kpis', name: 'operator_dashboard_kpis')]` method
- Return `JsonResponse` from `OperatorKpiService::collectKpis()`
- Refactor `live()` action to use `OperatorKpiService` for initial KPIs

### Task 3: Enhance dashboard template

**File:** `backend/templates/operator/dashboard_live.html.twig`

- Add 2 KPI cards: Success Rate (7d), Vehicles Active
- Add Top 3 Drivers mini-leaderboard (below KPI row)
- Wire Mercure route events to debounced KPI refresh via AJAX
- Add `refreshKpis()` method to Alpine.js component

### Task 4: Commit and push
