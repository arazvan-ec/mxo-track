# Plan — Fase 2: Shipments + Customers + Drivers List Pages

**Fecha:** 2026-04-10
**Spec:** `docs/superpowers/specs/2026-04-10-responsive-data-table-phase2.md`
**Branch:** `claude/improve-mobile-table-scroll-R2qex`

## Phase 1: v0

### [parallel] Wave 1: API endpoints (3 in parallel)

**1a. Shipment list API**
- File: `backend/src/Controller/Api/Admin/ShipmentListApiController.php`
- Route: `GET /api/admin/shipments` — page, limit, customer (publicId)
- Query: Shipment joined with Customer, filter deletedAt IS NULL, optional customer filter
- Response: PaginatedResponse with ShipmentListItem fields
- → produces: API endpoint

**1b. Customer list API**
- File: `backend/src/Controller/Api/Admin/CustomerListApiController.php`
- Route: `GET /api/admin/customers` — page, limit
- Query: Customer + separate aggregations for userCount + primary email
- Response: PaginatedResponse with CustomerListItem fields
- → produces: API endpoint

**1c. Driver list API**
- File: `backend/src/Controller/Api/Admin/DriverListApiController.php`
- Route: `GET /api/admin/drivers` — page, limit
- Query: User filtered by ROLE_DRIVER via JSON_TEXT
- Response: PaginatedResponse with DriverListItem fields
- → produces: API endpoint

### [parallel] Wave 2: Hooks + Pages (3 in parallel, needs Wave 1)

**2a. Shipments hook + page**
- Hook: `frontend/src/api/hooks/useAdminShipments.ts`
- Page: `frontend/src/pages/admin/AdminShipmentsListPage.tsx`
- Features: Customer filter dropdown, PriorityBadge (5 levels with colors), CargoDisplay (kg/m³/bultos)
- → produces: working shipments page

**2b. Customers hook + page**
- Hook: `frontend/src/api/hooks/useAdminCustomers.ts`
- Page: `frontend/src/pages/admin/AdminCustomersListPage.tsx`
- Features: ActiveBadge, user count display, primary email
- → produces: working customers page

**2c. Drivers hook + page**
- Hook: `frontend/src/api/hooks/useAdminDrivers.ts`
- Page: `frontend/src/pages/admin/AdminDriversListPage.tsx`
- Features: ActiveBadge, name with email fallback, Horario + Editar actions
- → produces: working drivers page

### Wave 3: Router + Navigation + Verification (sequential)

**3a. Update router.tsx** — add 3 routes
**3b. Update NavigationController.php** — update 3 nav links to /app/ prefix
**3c. Build verification** — `cd frontend && npm run build`
