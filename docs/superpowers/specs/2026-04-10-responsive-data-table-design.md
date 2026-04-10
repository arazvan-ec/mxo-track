# Spec — ResponsiveDataTable + Migración de Listados a React SPA

**Fecha:** 2026-04-10
**Tipo:** Feature (code change)
**Branch:** `claude/improve-mobile-table-scroll-R2qex`

## Problema

Las 19 tablas Twig en el portal admin/customer usan `overflow-x-auto` como única estrategia responsiva. En móvil (< 640px viewport), las columnas importantes (Estado, Acciones) quedan fuera de pantalla, el header bar queda parcialmente oculto, y no hay affordance de scroll horizontal. El resultado es una UX inutilizable en dispositivos móviles.

## Decisión de diseño

**Enfoque elegido: Data Dense Cards + migración completa a React SPA (Camino 2)**

En lugar de parchar las tablas Twig con CSS responsivo, migrar las páginas de listado al React SPA existente (`/app/*`). Crear un componente `<ResponsiveDataTable>` que renderiza como tabla en desktop (≥ 1024px) y como cards compactas en móvil.

**Alternativas descartadas:**
- (A) Smart Responsive Table — columnas priorizadas con hide/show. No resuelve el problema fundamental: una tabla no es el formato correcto para 360px.
- (B) Card Morph CSS-only — CSS Grid con `data-label`. Mantiene Twig, no unifica frontend.
- (Camino 1) React widget en Twig — migración parcial que mantiene dos mundos.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| 19 tablas Twig (`admin/route/index`, etc.) | **Transform** | Se reemplazan por React pages. Las rutas Twig siguen funcionando (coexistencia) |
| `overflow-x-auto` pattern | **Omit** | Reemplazado por responsive card/table |
| TanStack React Query + `api.get()` | **Include** | Patrón de data fetching establecido |
| `types.ts` (RouteData, FleetVehicle, etc.) | **Include** | Se extienden con tipos de listado |
| `router.tsx` (13 rutas SPA) | **Include** | Se agregan rutas de listado |
| `NavigationSidebar.tsx` | **Include** | Se agregan links a las nuevas páginas |
| Design tokens CSS (`--color-*`, `--card-*`) | **Include** | Cards usan el sistema de tokens existente |
| `theme-card` class | **Include** | Base visual para cards |
| `badge-*` classes (blue, amber, green, red) | **Include** | Badges de estado |
| `AppLayout.tsx` | **Include** | Layout wrapper para todas las páginas |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| BottomSheet | Omit | Solo para map views, no aplica a listados |
| Swipe Actions | **Defer** | Complejidad de touch gestures. v0 usa expand-to-reveal. Evaluar en Phase 2 |
| Customer portal pages | **Defer** | Misma estructura, se migra después con los componentes ya creados |
| Server-side sorting | **Defer** | v0 usa sorting client-side. Server-side cuando haya > 500 items |

## Arquitectura

### Component: `<ResponsiveDataTable<T>>`

Componente genérico que acepta:

```tsx
interface ColumnDef<T> {
  key: keyof T | string;
  label: string;
  priority: 'primary' | 'secondary';  // primary = visible en mobile card
  mobile: 'title' | 'subtitle' | 'badge' | 'detail' | 'hidden';
  render?: (value: unknown, row: T) => React.ReactNode;
  align?: 'left' | 'right' | 'center';
}

interface ResponsiveDataTableProps<T> {
  columns: ColumnDef<T>[];
  data: T[];
  keyField: keyof T;
  actions?: (row: T) => ActionDef[];
  onRowClick?: (row: T) => void;
  emptyMessage?: string;
  isLoading?: boolean;
}
```

**Desktop (≥ 1024px):** Renderiza `<table>` estándar con `theme-card`, `divide-y`, headers uppercase.
**Mobile (< 1024px):** Renderiza cards con layout:

```
┌──────────────────────────────────┐
│▌ {title}                         │  ← mobile: 'title', barra lateral = badge color
│▌ {subtitle1} · {subtitle2}      │  ← mobile: 'subtitle', joined con ·
│▌ {detail custom render}          │  ← mobile: 'detail'
│▌                      {badge}    │  ← mobile: 'badge'
├── tap para expandir ─────────────┤
│  {secondary columns}             │  ← priority: 'secondary'
│  [Acción1] [Acción2] [Acción3]   │  ← actions
└──────────────────────────────────┘
```

### Component: `<FilterBar>`

```tsx
interface FilterChip {
  key: string;
  label: string;
  value: string;
  color?: string;  // for status tabs
  count?: number;  // optional count badge
}

interface FilterBarProps {
  chips: FilterChip[];
  activeChip: string;
  onChipClick: (key: string) => void;
  advancedFilters?: React.ReactNode;  // slot for dropdown filters
}
```

**Mobile:** Chips scroll horizontalmente, sticky bajo TopBar. Filtros avanzados en collapsible drawer.
**Desktop:** Chips inline + filtros avanzados visible.

### Component: `<Pagination>`

```tsx
interface PaginationProps {
  page: number;
  totalPages: number;
  onPageChange: (page: number) => void;
}
```

### API Endpoints (nuevos)

| Endpoint | Method | Params | Response |
|----------|--------|--------|----------|
| `/api/admin/routes` | GET | `?status=&page=&limit=&date_from=&date_to=&driver=&customer=` | `{ items: RouteListItem[], total: number, page: number, pages: number }` |
| `/api/admin/vehicles` | GET | `?page=&limit=` | `{ items: VehicleListItem[], total: number, page: number, pages: number }` |
| `/api/admin/shipments` | GET | `?page=&limit=&customer=` | `{ items: ShipmentListItem[], ... }` |
| `/api/admin/customers` | GET | `?page=&limit=` | `{ items: CustomerListItem[], ... }` |
| `/api/admin/drivers` | GET | `?page=&limit=` | `{ items: DriverListItem[], ... }` |

### TypeScript Types (nuevos en `types.ts`)

```tsx
// Paginated response wrapper
interface PaginatedResponse<T> {
  items: T[];
  total: number;
  page: number;
  pages: number;
}

interface RouteListItem {
  publicId: string;
  name: string;
  customerName: string | null;
  vehicleName: string | null;
  driverName: string | null;
  driverEmail: string | null;
  status: 'PLANNED' | 'ACTIVE' | 'DONE' | 'CANCELLED';
  deliveredStops: number;
  totalStops: number;
}

interface VehicleListItem {
  publicId: string;
  name: string;
  traccarDeviceId: number | null;
  active: boolean;
  maxWeightKg: number | null;
  maxVolumeM3: number | null;
  maxParcels: number | null;
  lastPosition: { lat: number; lng: number } | null;
  createdAt: string;
}
```

### Páginas React (nuevas)

| Página | Ruta | Componente |
|--------|------|-----------|
| Rutas admin | `/app/admin/routes` | `AdminRoutesListPage` |
| Vehículos admin | `/app/admin/vehicles` | `AdminVehiclesListPage` |
| Envíos admin | `/app/admin/shipments` | `AdminShipmentsListPage` |
| Clientes admin | `/app/admin/customers` | `AdminCustomersListPage` |
| Conductores admin | `/app/admin/drivers` | `AdminDriversListPage` |

## Scope de esta implementación

**Fase 1 (ahora):** ResponsiveDataTable + FilterBar + Pagination + Rutas + Vehículos
**Fase 2 (futuro):** Envíos + Clientes + Conductores
**Fase 3 (futuro):** Customer portal (mismos componentes, scope por tenant)
