# Plan — Customer Advanced Filters

**Date:** 2026-04-12
**Type:** code change
**Branch:** `claude/add-customer-filters-ev8cG`

## Goal

Add advanced filters (search by name + frequency dropdown) to the Customers admin list view, following the exact pattern established in Routes and Shipments views.

## Design

- **Search**: text input filtering `name` via `LIKE %query%` (case-insensitive)
- **Frequency**: dropdown filtering `frequency` enum field (4 values + null → "Todas")
- **Pattern**: Same grid layout + FilterBar slot used in AdminRoutesListPage

## Phase 1 (v0)

### Wave 1: Backend filters

**Task 1 — Add search + frequency params to CustomerListApiController**
- Add `search` (string) and `frequency` (string) query params
- Apply `c.name LIKE :search` with `%query%` wrapping (both `$qb` and `$countQb`)
- Apply `c.frequency = :frequency` via `ClientFrequency::tryFrom()` (both QBs)
- Add `/filters` endpoint returning available frequency values with labels
- Files: `backend/src/Controller/Api/Admin/CustomerListApiController.php`

### Wave 2: Frontend filters

**Task 2 — Update hook + page component**
- Add `search` and `frequency` params to `CustomerListParams` and `useAdminCustomers`
- Add `useCustomerFilters` hook fetching `/api/admin/customers/filters`
- Add filter state (`search`, `frequency`) to `AdminCustomersListPage`
- Build `advancedFilters` JSX (grid: text input + select dropdown)
- Pass to FilterBar with `advancedFiltersOpen` auto-open logic
- Files: `frontend/src/api/hooks/useAdminCustomers.ts`, `frontend/src/pages/admin/AdminCustomersListPage.tsx`

### Wave 3: Verification

- PHP lint (`make lint`)
- Frontend build (`cd frontend && npm run build`)
- Manual review of filter logic
