# Plan — ListFilterApplier Service

**Date:** 2026-04-12
**Branch:** `claude/add-customer-filters-ev8cG`

## Phase 1 (v0)

### Wave 1: Create service + value object

**Task 1 — FilterDefinition value object**
- Create `backend/src/Service/Admin/FilterDefinition.php`
- Static factories: `boolean()`, `like()`, `enum()`, `dateFrom()`, `dateTo()`, `entity()`
- `withCountJoin()` for Route's re-alias case
- Each factory validates/parses the raw value (empty → skip, bad date → skip, etc.)

**Task 2 — ListFilterApplier service**
- Create `backend/src/Service/Admin/ListFilterApplier.php`
- `apply(QueryBuilder $qb, QueryBuilder $countQb, array $filters): void`
- Iterates definitions, skips inactive (empty/invalid), applies andWhere+setParameter to both QBs
- Handles countQb join when `withCountJoin` is set

### [parallel] Wave 2: Refactor controllers (3 simple)

**Task 3a — CustomerListApiController**
- Inject `ListFilterApplier`, replace inline filter logic with `$this->filterApplier->apply()`

**Task 3b — VehicleListApiController**
- Same refactor (bool + date range)

**Task 3c — DriverListApiController**
- Same refactor (bool + date range)

### [parallel] Wave 3: Refactor controllers (2 complex)

**Task 4a — ShipmentListApiController**
- Refactor with entity lookup (Customer by publicId) + enum + date range

**Task 4b — RouteListApiController**
- Refactor with join re-aliasing via `withCountJoin()` + enum + date range + entity

### Wave 4: Verification

- PHP lint on all 7 files
- Frontend build (`cd frontend && npm run build`)
- Review each controller's filter output matches original logic
