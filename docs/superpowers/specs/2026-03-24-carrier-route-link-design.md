# Carrier Route Link — Design Spec

**Date:** 2026-03-24
**Type:** Enhancement
**Bounded Context:** Pragmatic (Admin UI)
**Complexity:** S

## Goal

Add a "Ruta" link in the admin drivers list that navigates directly to the driver's active/planned route.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| Horario link | Include | Keep as-is |
| Editar link | Include | Keep as-is |
| Desactivar button | Include | Keep as-is |
| Email, Nombre, Estado, Creado columns | Include | Keep as-is |
| Pagination | Include | Keep as-is |

No omissions — all inventory items addressed.

## Design

### Controller Change (`DriverAdminController::index`)

After querying drivers, query the most recent ACTIVE or PLANNED route for each driver:

```sql
SELECT r.driver_id, r.public_id
FROM route r
WHERE r.driver_id IN (:driverIds)
  AND r.status IN ('ACTIVE', 'PLANNED')
ORDER BY r.id DESC
```

Build a map `driverId → routePublicId` (first match per driver = most recent) and pass to template as `driverRoutes`.

### Template Change (`admin/driver/index.html.twig`)

In the actions column, before "Horario":
- If `driverRoutes[driver.id]` exists → show link "Ruta" (purple/indigo) pointing to `admin_routes_show` with the route's publicId
- If not → don't show the link

## Approach

Approach 3 from brainstorming: direct link to active route. Fallback: no link shown when no active route exists.
