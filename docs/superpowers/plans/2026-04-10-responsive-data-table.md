# Plan — ResponsiveDataTable + Migración de Listados a React SPA

**Fecha:** 2026-04-10
**Spec:** `docs/superpowers/specs/2026-04-10-responsive-data-table-design.md`
**Branch:** `claude/improve-mobile-table-scroll-R2qex`

## Scope activo: Fase 1 — Fundación + Rutas + Vehículos

---

## Phase 1: v0 — Implementación funcional

### Wave 1: Fundación — Componentes compartidos + tipos [parallel]

**1a. TypeScript types para listados**
- Archivo: `frontend/src/api/types.ts`
- Añadir: `PaginatedResponse<T>`, `RouteListItem`, `VehicleListItem`, `ShipmentListItem`, `CustomerListItem`, `DriverListItem`
- TDD: TypeScript compila sin errores
- → produce: tipos compartidos para hooks y componentes

**1b. ResponsiveDataTable component**
- Archivo: `frontend/src/components/data-table/ResponsiveDataTable.tsx`
- Implementar: `ColumnDef<T>`, `ResponsiveDataTableProps<T>`
- Desktop: `<table>` con `theme-card`, headers, `divide-y`, row hover
- Mobile: cards con title/subtitle/badge/detail layout, expand toggle para secondary columns y actions
- Breakpoint: `useMediaQuery('(min-width: 1024px)')` o Tailwind `lg:`
- CSS: usar design tokens existentes (`--color-surface-elevated`, `--card-*`, `badge-*`)
- Barra lateral de color en card = color del badge de estado
- TDD: componente renderiza en ambos modos
- → produce: componente core reutilizable

**1c. FilterBar component**
- Archivo: `frontend/src/components/data-table/FilterBar.tsx`
- Chips scrollables horizontalmente en mobile (overflow-x-auto + snap)
- Sticky bajo TopBar (sticky top-[topbar-height])
- Active chip = estilo filled, inactive = outline
- Slot para filtros avanzados (collapsible)
- Counters opcionales en chips
- TDD: chips renderizan, click callback funciona
- → produce: componente de filtros reutilizable

**1d. Pagination component**
- Archivo: `frontend/src/components/data-table/Pagination.tsx`
- "Página X de Y" + botones Anterior/Siguiente
- Usa `theme-card` para botones, mismos estilos que Twig actual
- TDD: navegación funciona
- → produce: componente de paginación reutilizable

### Wave 2: API endpoints backend [parallel]

**2a. Route list API endpoint**
- Archivo: `backend/src/Controller/Api/Admin/RouteListApiController.php`
- Ruta: `GET /api/admin/routes`
- Query params: `status`, `page` (default 1), `limit` (default 25), `date_from`, `date_to`, `driver`, `customer`
- Response: `PaginatedResponse<RouteListItem>` — incluye `customerName`, `vehicleName`, `driverName`, `deliveredStops`, `totalStops`
- Seguridad: `#[IsGranted('ROLE_ADMIN')]`
- Multi-tenancy: usa el patrón existente de los controllers admin
- Endpoint para obtener opciones de filtro: drivers y customers disponibles (puede ser inline en la misma response o endpoint separado `/api/admin/routes/filters`)
- TDD: endpoint devuelve JSON con estructura correcta
- → produce: datos para la página de rutas React

**2b. Vehicle list API endpoint**
- Archivo: `backend/src/Controller/Api/Admin/VehicleListApiController.php`
- Ruta: `GET /api/admin/vehicles`
- Query params: `page`, `limit`
- Response: `PaginatedResponse<VehicleListItem>` — incluye `lastPosition`, capacidades
- Seguridad: `#[IsGranted('ROLE_ADMIN')]`
- TDD: endpoint devuelve JSON con estructura correcta
- → produce: datos para la página de vehículos React

### Wave 3: React hooks + páginas [parallel] (necesita Wave 1 + Wave 2)

**3a. useAdminRoutes hook + AdminRoutesListPage**
- Hook: `frontend/src/api/hooks/useAdminRoutes.ts`
  - `useAdminRoutes(params)` — TanStack Query, queryKey incluye filtros
  - `useRouteFilters()` — fetch drivers/customers para dropdowns
- Página: `frontend/src/pages/admin/AdminRoutesListPage.tsx`
  - Header: "Rutas" + botón "Nueva ruta" (link a `/admin/routes/new` Twig)
  - FilterBar: chips [Todas, Planificadas, Activas, Completadas, Canceladas] con colores
  - Filtros avanzados: fecha desde/hasta, transportista dropdown, cliente dropdown
  - ResponsiveDataTable con columns:
    | key | label | priority | mobile | render |
    |-----|-------|----------|--------|--------|
    | name | Nombre | primary | title | — |
    | vehicleName | Vehículo | primary | subtitle | icon 🚚 |
    | driverName | Transportista | primary | subtitle | icon 👤 |
    | status | Estado | primary | badge | StatusBadge |
    | progress | Progreso | primary | detail | ProgressBar |
    | customerName | Cliente | secondary | detail | — |
  - Actions: Ver (link SPA), Editar (link Twig), Análisis (if DONE, link SPA), Cancelar (confirm + API)
  - Pagination
- Router: agregar `/app/admin/routes` a `router.tsx`
- TDD: página renderiza datos, filtros funcionan, mobile muestra cards
- → produce: página completa de rutas en React

**3b. useAdminVehicles hook + AdminVehiclesListPage**
- Hook: `frontend/src/api/hooks/useAdminVehicles.ts`
  - `useAdminVehicles(params)` — TanStack Query
- Página: `frontend/src/pages/admin/AdminVehiclesListPage.tsx`
  - Header: "Vehículos" + botón "Nuevo vehículo" (link a Twig)
  - Sin FilterBar (no tiene filtros de status)
  - ResponsiveDataTable con columns:
    | key | label | priority | mobile | render |
    |-----|-------|----------|--------|--------|
    | name | Nombre | primary | title | — |
    | active | Estado | primary | badge | ActiveBadge (Activo/Inactivo) |
    | capacity | Capacidad | primary | detail | CapacityDisplay (kg/m3/paq) |
    | traccarDeviceId | Traccar ID | secondary | detail | — |
    | lastPosition | Última posición | secondary | detail | LatLng o "Sin señal" |
    | createdAt | Creado | secondary | hidden | date format |
  - Actions: Editar (link Twig), Desactivar (confirm + API)
  - Pagination
- Router: agregar `/app/admin/vehicles` a `router.tsx`
- TDD: página renderiza datos, mobile muestra cards
- → produce: página completa de vehículos en React

### Wave 4: Navegación + integración [sequential] (necesita Wave 3)

**4a. Actualizar NavigationSidebar**
- Archivo: `frontend/src/components/layout/NavigationSidebar.tsx`
- Actualizar links de "Rutas" y "Vehículos" para apuntar a `/app/admin/routes` y `/app/admin/vehicles` en lugar de las URLs Twig
- → produce: navegación unificada

**4b. Verificación final**
- `cd frontend && npm run build` — TypeScript clean + Vite build OK
- Verificar visual: desktop muestra tabla, mobile muestra cards
- → produce: build limpio y verificado

---

## Phase 2: Mature — Refinamiento (post-v0)

- Swipe actions en mobile cards (touch gesture handler)
- Sorting por columna (client-side, icon en header)
- Search inline (filtro de texto libre)
- Empty states con ilustración
- Skeleton loading cards
- Animación de transición tabla ↔ cards

---

## Fase 2 del proyecto (futuro): Más páginas

Cada página sigue el mismo patrón (Wave 2 + Wave 3 de arriba):

### Envíos (admin)
- API: `GET /api/admin/shipments` — 8 columnas, filtro por customer
- Página: `AdminShipmentsListPage.tsx`
- Columns mobile: reference (title), recipient+address (subtitle), priority (badge), cargo (detail)

### Clientes (admin)
- API: `GET /api/admin/customers` — 7 columnas, sin filtros
- Página: `AdminCustomersListPage.tsx`
- Columns mobile: name (title), email+phone (subtitle), status (badge), address+users (detail)

### Conductores (admin)
- API: `GET /api/admin/drivers` — 5 columnas, sin filtros
- Página: `AdminDriversListPage.tsx`
- Columns mobile: name (title), email (subtitle), status (badge), createdAt (detail)

---

## Fase 3 del proyecto (futuro): Customer portal

- Rutas customer: misma página con scope tenant (API ya filtra por `customer_id`)
- Envíos customer: igual
- Reutiliza 100% de `ResponsiveDataTable`, `FilterBar`, `Pagination`

---

## Resumen de archivos

### Nuevos (Fase 1 activa)
| Archivo | Propósito |
|---------|-----------|
| `frontend/src/components/data-table/ResponsiveDataTable.tsx` | Componente core tabla/cards |
| `frontend/src/components/data-table/FilterBar.tsx` | Chips de filtro + avanzados |
| `frontend/src/components/data-table/Pagination.tsx` | Paginación |
| `frontend/src/api/hooks/useAdminRoutes.ts` | Hook datos de rutas |
| `frontend/src/api/hooks/useAdminVehicles.ts` | Hook datos de vehículos |
| `frontend/src/pages/admin/AdminRoutesListPage.tsx` | Página listado de rutas |
| `frontend/src/pages/admin/AdminVehiclesListPage.tsx` | Página listado de vehículos |
| `backend/src/Controller/Api/Admin/RouteListApiController.php` | API list rutas |
| `backend/src/Controller/Api/Admin/VehicleListApiController.php` | API list vehículos |

### Modificados (Fase 1 activa)
| Archivo | Cambio |
|---------|--------|
| `frontend/src/api/types.ts` | Añadir tipos PaginatedResponse, RouteListItem, VehicleListItem |
| `frontend/src/router.tsx` | Añadir 2 rutas |
| `frontend/src/components/layout/NavigationSidebar.tsx` | Actualizar links |
