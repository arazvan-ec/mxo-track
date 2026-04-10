# Spec Addendum — Fase 2: Shipments + Customers + Drivers List Pages

**Fecha:** 2026-04-10
**Extends:** `docs/superpowers/specs/2026-04-10-responsive-data-table-design.md`
**Branch:** `claude/improve-mobile-table-scroll-R2qex`

## Problema

Las 3 páginas de listado restantes (Envíos, Clientes, Conductores) siguen usando tablas Twig con `overflow-x-auto`, la misma UX inutilizable en móvil que las páginas de Rutas y Vehículos ya migradas.

## Approach — Replicar patrón Phase 1

**Alternativa evaluada:** Crear componentes custom por página vs reutilizar `ResponsiveDataTable` existente.
- **Opción A: Componentes custom** — Cada página tiene su propio layout de cards. Ventaja: máximo control visual. Desventaja: 3x código, 3x mantenimiento, inconsistencia visual.
- **Opción B: Reutilizar ResponsiveDataTable (ELEGIDA)** — Las 3 páginas usan el mismo componente con column definitions declarativas. Ventaja: consistencia, cero duplicación. Desventaja: ninguna real, el componente ya soporta todos los casos (badges, subtitles, detail renders).
- **Trade-off:** Opción B sacrifica personalización extrema por consistencia. Dado que las 3 páginas son CRUD estándar sin layouts especiales, la personalización no aporta valor.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| `ResponsiveDataTable` component | **Include** | Core reutilizable, ya probado con Rutas y Vehículos |
| `FilterBar` component | **Include** | Solo Shipments lo necesita (filtro por customer) |
| `Pagination` component | **Include** | Las 3 páginas tienen paginación |
| Types en `types.ts` (ShipmentListItem, CustomerListItem, DriverListItem) | **Include** | Ya definidos en Phase 1 |
| `badge-*` CSS classes | **Include** | Para PriorityBadge en Shipments |
| Twig templates existentes | **Omit** | Se mantienen funcionales como fallback, pero la navegación apuntará a React |

## Scope

Migrate 3 remaining admin list pages to React SPA using the `ResponsiveDataTable` component created in Phase 1.

## Pages

### Shipments (8 columns, 1 filter)
- **API:** `GET /api/admin/shipments` — params: `page`, `limit`, `customer`
- **Entity:** `Shipment` (joined with Customer). Filter: `deletedAt IS NULL`
- **Columns:**

| key | label | priority | mobile | render |
|-----|-------|----------|--------|--------|
| reference | Referencia | primary | title | — |
| recipientName | Destinatario | primary | subtitle | — |
| address | Dirección | primary | subtitle | truncate |
| priority | Prioridad | primary | badge | PriorityBadge (5 levels: CRITICAL/URGENT/HIGH/NORMAL/LOW) |
| cargo | Carga | primary | detail | CargoDisplay (kg/m³/bultos) |
| customerName | Cliente | secondary | detail | — |
| createdAt | Creado | secondary | hidden | date format |

- **Filter:** Customer dropdown (from separate customers list)
- **Actions:** Editar (Twig link)

### Customers (7 columns, no filters)
- **API:** `GET /api/admin/customers` — params: `page`, `limit`
- **Entity:** `Customer`. Extra queries: user count per customer, primary email per customer
- **Columns:**

| key | label | priority | mobile | render |
|-----|-------|----------|--------|--------|
| name | Nombre | primary | title | — |
| email | Email | primary | subtitle | — |
| phone | Teléfono | primary | subtitle | — |
| active | Estado | primary | badge | ActiveBadge |
| address | Dirección | secondary | detail | — |
| userCount | Usuarios | secondary | detail | — |

- **Actions:** Editar (Twig link)

### Drivers (5 columns, no filters)
- **API:** `GET /api/admin/drivers` — params: `page`, `limit`
- **Entity:** `User` filtered by `ROLE_DRIVER`
- **Columns:**

| key | label | priority | mobile | render |
|-----|-------|----------|--------|--------|
| name | Nombre | primary | title | fallback to email |
| email | Email | primary | subtitle | — |
| active | Estado | primary | badge | ActiveBadge |
| createdAt | Creado | secondary | hidden | date format |

- **Actions:** Horario (Twig link), Editar (Twig link)

## Omission Decisions
| Element | Decision | Justification |
|---------|----------|---------------|
| Delete/Deactivate actions via API | Defer | Keep as Twig links for now — requires CSRF token handling in React |
