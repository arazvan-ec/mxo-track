---
type: process
tags: []
files_touched: []
patterns: []
outcome: success
outcome_verified_at: null
regressions_later: []
pr_number: 245
estimated_lines: null
actual_lines: null
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-12 — Customer Advanced Filters

**Type:** code change
**Branch:** `claude/add-customer-filters-ev8cG`

## Brainstorming

- **Alternatives:** 3 enfoques evaluados (A: search+frequency, B: +address, C: search only)
- **Chosen:** Enfoque A — búsqueda por nombre + dropdown frecuencia
- **Rationale:** Cubre los 2 filtros de mayor valor de negocio sin ruido. Sigue patrón existente de Routes/Shipments.

## Planning

- **Tasks:** 2 (backend filters + frontend wiring)
- **Files affected:** 3 (`CustomerListApiController.php`, `useAdminCustomers.ts`, `AdminCustomersListPage.tsx`)
- **Pattern followed:** Same as AdminRoutesListPage (FilterBar slot + dropdown + query params + dual QueryBuilder)

## Implementation

- **Backend:** Added `search` (LIKE case-insensitive on `c.name`) and `frequency` (ClientFrequency enum) query params to both `$qb` and `$countQb`. New `/filters` endpoint returns enum values with labels.
- **Frontend:** Added `search` and `frequency` state, `useCustomerFilters` hook, `advancedFilters` JSX with 2-col grid (text input + select dropdown), wired to FilterBar with auto-open.
- **Lines changed:** +79 across 3 files
- **Blockers:** Hook gate required explicit approval keyword from user ("adelante"); initial "Igual que las otras vistas" wasn't matched by approval regex.

## Verification

- TypeScript build: ✅ (`tsc -b && vite build`)
- PHP lint: ✅
- No new test files (pragmatic context, controller-level filtering follows established untested pattern across Routes/Shipments/Vehicles/Drivers)

## Lessons

- The `user_approved` gate only matches specific keywords — "igual" is not in the approval regex. Future sessions should present approval as a yes/no question.
- Customer entity lacks `createdAt` — date range filters impossible without migration.
