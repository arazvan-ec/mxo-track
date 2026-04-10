# Plan — Advanced Filters in All Views

**Date:** 2026-04-10
**Branch:** `claude/add-advanced-filter-routes-6HmHQ`
**Reference:** `AdminRoutesListPage.tsx` (complete FilterBar implementation)

## Goal

Replicate the advanced filter pattern from Routes to all 4 remaining admin list views:
Shipments, Vehicles, Customers, Drivers.

## Pattern (from Routes)

Each view gets:
1. **FilterBar chips** — quick status/category filter
2. **Advanced filters panel** — collapsible panel with date range + dropdowns
3. **Backend query params** — filter the main query AND count query
4. **Frontend hook params** — pass filters to API

## Phase 1 (v0): All filters working

### [parallel] Wave 1: Backend controllers (4 tasks)

All controllers are independent — they touch different files.

- **1a: ShipmentListApiController** — Add `priority` + `date_from` + `date_to` query params
  - Filter `s.priority` by enum name (string match via `ShipmentPriority::tryFrom()` won't work since it's int-backed — use name match)
  - Filter `s.createdAt` by date range (same pattern as routes)
  - Apply to both main qb AND countQb
  → produces: 3 new query params on `/api/admin/shipments`

- **1b: VehicleListApiController** — Add `active` + `date_from` + `date_to` query params
  - Filter `v.isActive` by boolean (string 'true'/'false' → bool)
  - Filter `v.createdAt` by date range
  - Refactor to use countQb pattern (currently separate query, needs filters applied)
  → produces: 3 new query params on `/api/admin/vehicles`

- **1c: CustomerListApiController** — Add `active` + `date_from` + `date_to` query params
  - Filter `c.isActive` by boolean
  - Filter `c.createdAt` by date range (need to verify Customer has createdAt)
  - Refactor to use countQb pattern
  → produces: 3 new query params on `/api/admin/customers`

- **1d: DriverListApiController** — Add `active` + `date_from` + `date_to` query params
  - Filter `u.isActive` by boolean
  - Filter `u.createdAt` by date range
  - Refactor to use countQb pattern
  → produces: 3 new query params on `/api/admin/drivers`

### [parallel] Wave 2: Frontend hooks (4 tasks)

Each hook is independent. Depends on Wave 1 param names.

- **2a: useAdminShipments.ts** — Add `priority`, `date_from`, `date_to` to ShipmentListParams
- **2b: useAdminVehicles.ts** — Add `active`, `date_from`, `date_to` to VehicleListParams
- **2c: useAdminCustomers.ts** — Add `active`, `date_from`, `date_to` to CustomerListParams
- **2d: useAdminDrivers.ts** — Add `active`, `date_from`, `date_to` to DriverListParams

### [parallel] Wave 3: Frontend pages (4 tasks)

Each page is independent. Depends on Wave 2 hooks.

- **3a: AdminShipmentsListPage.tsx** — Add FilterBar with priority chips + advanced panel (customer dropdown + date range)
  - Chips: Todas, Crítica, Urgente, Alta, Normal, Baja (6 chips)
  - Advanced: Customer dropdown (existing) + date_from + date_to
  - Remove old inline customer filter

- **3b: AdminVehiclesListPage.tsx** — Add FilterBar with active chips + advanced panel (date range)
  - Chips: Todos, Activos, Inactivos (3 chips)
  - Advanced: date_from + date_to

- **3c: AdminCustomersListPage.tsx** — Add FilterBar with active chips + advanced panel (date range)
  - Chips: Todos, Activos, Inactivos (3 chips)
  - Advanced: date_from + date_to

- **3d: AdminDriversListPage.tsx** — Add FilterBar with active chips + advanced panel (date range)
  - Chips: Todos, Activos, Inactivos (3 chips)
  - Advanced: date_from + date_to

### Wave 4: Verification

- `cd frontend && npm run build` (TypeScript + Vite)
- `cd backend && make lint` (PHP lint)

## Files touched

| File | Change |
|------|--------|
| `backend/src/Controller/Api/Admin/ShipmentListApiController.php` | +priority, +date_from, +date_to filters |
| `backend/src/Controller/Api/Admin/VehicleListApiController.php` | +active, +date_from, +date_to filters, countQb refactor |
| `backend/src/Controller/Api/Admin/CustomerListApiController.php` | +active, +date_from, +date_to filters, countQb refactor |
| `backend/src/Controller/Api/Admin/DriverListApiController.php` | +active, +date_from, +date_to filters, countQb refactor |
| `frontend/src/api/hooks/useAdminShipments.ts` | +params |
| `frontend/src/api/hooks/useAdminVehicles.ts` | +params |
| `frontend/src/api/hooks/useAdminCustomers.ts` | +params |
| `frontend/src/api/hooks/useAdminDrivers.ts` | +params |
| `frontend/src/pages/admin/AdminShipmentsListPage.tsx` | +FilterBar, remove inline filter |
| `frontend/src/pages/admin/AdminVehiclesListPage.tsx` | +FilterBar |
| `frontend/src/pages/admin/AdminCustomersListPage.tsx` | +FilterBar |
| `frontend/src/pages/admin/AdminDriversListPage.tsx` | +FilterBar |
