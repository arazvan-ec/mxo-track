# Widget System

**Última actualización:** 2026-04-19
**Estado:** Vigente

Consult this module when: adding a new widget, editing `PageLayout*` entities,
modifying `WidgetRenderer` / `registry.ts`, wiring widgets to a map page's
`BottomSheet`, or changing the `PageLayoutEditorPage`.

## Architecture Overview

The widget system is a **registry-driven, layout-persisted UI composition** that
lets admins arrange per-page widgets per `SheetState` (collapsed/half/full) from
the browser. Backend stores the layout; frontend resolves it and renders.

```
Backend (catalog + persistence)                Frontend (registry + render)
─────────────────────────────────              ──────────────────────────────
WidgetDefinition (one row per WidgetType)  ←→  WIDGET_REGISTRY[type] = { component, ... }
    │ (catalog + active flag)
PageLayout (pageKey, customer?)            ──→  usePageLayout(pageKey) → LayoutConfig
    └─ PageLayoutWidget (sheetState, position)   └─ WidgetRenderer iterates widgets[state]
```

**Contract:** the string value of `WidgetType` (PHP enum) MUST match a key in
`WIDGET_REGISTRY` (TS) and a literal in `frontend/src/types/layout.ts#WidgetType`.
Three places — one source of truth by string value.

**Scope resolution:** `PageLayoutRepository::findForPage($pageKey, $customer)`
returns the customer-scoped layout if present, else the global (`customer_id IS
NULL`). Unique constraint `(page_key, customer_id)`.

## Backend Entities

| Entity | Table | Key columns | Purpose |
|--------|-------|------------|---------|
| `WidgetDefinition` | `widget_definition` | `type` (unique, `WidgetType`), `label`, `description`, `previewImage`, `active` | Catalog row per widget type. Admin toggles `active` via `/api/admin/widgets/{id}` PATCH |
| `PageLayout` | `page_layout` | `page_key` (`PageKey`), `customer_id` (nullable, unique pair) | One layout per page per tenant. `customer_id=NULL` = global default |
| `PageLayoutWidget` | `page_layout_widget` | `page_layout_id`, `widget_definition_id`, `sheet_state`, `position` | Placement row. Cascade delete from layout, orphanRemoval |

**Enums:**
- `PageKey` (10 cases): `fleet_map`, `test_routing`, `route_planner`, `route_analysis`, `route_detail`, `shipment_tracking`, `driver_route`, `customer_tracking`, `admin_dashboard`, `customer_dashboard`
- `SheetState` (3 cases): `collapsed`, `half`, `full`
- `WidgetType` (16 cases) — see inventory below

## API Surface

| Route | Controller | Auth | Purpose |
|-------|-----------|------|---------|
| `GET /api/admin/widgets` | `WidgetDefinitionApiController::list` | `ROLE_ADMIN` | Catalog list (all definitions with active flag) |
| `PATCH /api/admin/widgets/{publicId}` | `WidgetDefinitionApiController::patch` | `ROLE_ADMIN` | Toggle `active` |
| `GET /api/admin/page-layouts` | `PageLayoutApiController::list` | `ROLE_ADMIN` | List layouts (filter `?pageKey=`) |
| `GET /api/admin/page-layouts/{publicId}` | `::get` | `ROLE_ADMIN` | Single layout + widgets grouped by sheet state |
| `POST /api/admin/page-layouts` | `::create` | `ROLE_ADMIN` | Create layout with widgets payload |
| `PUT /api/admin/page-layouts/{publicId}` | `::update` | `ROLE_ADMIN` | Replace widgets (clears then re-adds) |
| `DELETE /api/admin/page-layouts/{publicId}` | `::delete` | `ROLE_ADMIN` | Remove layout |
| `GET /api/page-layouts/{pageKey}` | `PageLayoutResolverController` | `IS_AUTHENTICATED_FULLY` | Resolve layout for current user (customer-scoped fallback to global) |

**Resolver response shape** (consumed by `usePageLayout`):
```json
{
  "pageKey": "fleet_map",
  "scope": "global" | "customer" | "none",
  "widgets": {
    "collapsed": [{"type": "kpi_pills", "position": 0}],
    "half":      [...],
    "full":      [...]
  }
}
```

## Widget Inventory (16 in registry)

| Widget key | Component | Purpose | Collapsible |
|-----------|-----------|---------|:-----------:|
| `metric_pairs` | `MetricPairsWidget` | Scope/distance/time paired metrics (hero + delta) | |
| `route_card_list` | `RouteCardListWidget` | Scrollable cards with stops + metrics (dual-mode: TestRouting / Fleet) | |
| `map_legend` | `MapLegendWidget` | Route colors + markers legend | |
| `route_comparison` | `RouteComparisonWidget` | Before/after optimization comparison | |
| `kpi_pills` | `KpiPillsWidget` | Compact pills (vehicles/routes/pending) | |
| `stop_list` | `StopListWidget` | Ordered stops with status, ETA, selection | |
| `vehicle_info` | `VehicleInfoWidget` | Vehicle + driver + speed + skills | |
| `driver_info` | `DriverInfoWidget` | Progress bar + current stop (driver view) | |
| `shipment_details` | `ShipmentDetailsWidget` | Shipment card with recipient | |
| `delivery_timeline` | `DeliveryTimelineWidget` | Vertical timeline of delivery events | |
| `system_health` | `SystemHealthWidget` | 6 service cards (DB/Redis/Traccar/Mercure/OSRM/VROOM) | yes |
| `infrastructure_metrics` | `InfrastructureMetricsWidget` | Positions table / DB size / last ingestion | yes |
| `dashboard_kpis` | `DashboardKpisWidget` | 4 admin KPIs (routes/stops/imports/pos-per-hour) | yes |
| `mini_reports` | `MiniReportsWidget` | 7-day chart + top 5 drivers | yes |
| `activity_feed` | `ActivityFeedWidget` | Live position feed via Mercure SSE | yes |
| `customer_kpis` | `CustomerKpisWidget` | 5 customer KPIs (shipments/routes/deliveries/completed/exceptions) | yes |
| `customer_optimization` | `CustomerOptimizationWidget` | Km/time saved, success rate, savings % | yes |
| `reports_banner` | `ReportsBannerWidget` | CTA banner to reports | yes |

**Note:** `PlaceholderWidget.tsx` exists in `frontend/src/widgets/` but is NOT in
the registry — it is a dev scaffold and not wired to any `WidgetType`.

**Note:** Registry has 18 entries but `WidgetType` enum has 16 cases.
`customer_kpis` and `customer_optimization` are registered in TS but MISSING from
the backend `WidgetType` enum — they cannot currently be persisted to a layout
until added to `src/Enum/WidgetType.php`.

## Rendering: `WidgetRenderer` + `BottomSheet`

```tsx
const { layout } = usePageLayout('fleet_map');
const [sheetState, setSheetState] = useState<BottomSheetState>('collapsed');
const pageData = useMemo(() => ({ kpi, routes, vehicles, ... }), [...]);

<BottomSheet state={sheetState} onStateChange={setSheetState} ...>
  <WidgetRenderer layout={layout} sheetState={sheetState} pageData={pageData} />
</BottomSheet>
```

**Behavior of `WidgetRenderer`:**
- Reads `layout.widgets[sheetState]` (empty array if unset)
- Looks up each placement in `WIDGET_REGISTRY[type]` — missing entry = silent skip
- Passes `{ data: pageData, expanded: sheetState !== 'collapsed' }` as props
- `mode='page'` (used by `AdminDashboardPage`) wraps collapsible widgets in
  `CollapsibleWidget` with `summary` slot from `entry.summaryComponent`
- `mode='sheet'` (default, used by map pages inside `BottomSheet`) renders raw
  components without the collapsible wrapper

**Pages currently wiring `WidgetRenderer`:** `AdminDashboardPage`,
`OperatorDashboardPage`, `FleetMapPage`, `TestRoutingPage`, `RouteAnalysisPage`,
`RouteDetailPage`, `CustomerRouteDetailPage`, `DriverRoutePage`,
`PageLayoutEditorPage` (preview).

## `PageLayoutEditorPage`

Admin route `/app/admin/page-layouts`. Lets admins pick a page, arrange widgets
across the 3 sheet states, and preview the result.

- Page picker (`PAGE_OPTIONS`) lists the 8 non-dashboard pages (dashboards are
  edited differently — they use their own layout keys)
- Fetches `/api/admin/page-layouts?pageKey=X`, loads the global layout (`customerId=null`)
- Tracks `widgetsByState: Record<SheetStateName, WidgetType[]>` locally
- Save calls `PUT` if existing layout, `POST` otherwise
- Live preview uses `WidgetRenderer` with mock `pageData` so widgets must
  degrade gracefully when data fields are absent (see "Gotchas")

## How to Add a New Widget

1. **Add enum case** in `backend/src/Enum/WidgetType.php`:
   ```php
   case MY_NEW_WIDGET = 'my_new_widget';
   ```
2. **Create Doctrine migration** to insert the `widget_definition` row
   (reference pattern: `Version20260414120000_admin_dashboard_layout.php`).
3. **Add TS literal** in `frontend/src/types/layout.ts#WidgetType` union.
4. **Create component** `frontend/src/widgets/MyNewWidget.tsx` implementing
   `WidgetProps`:
   ```tsx
   import type { WidgetProps } from './types';
   interface MyData { foo?: string; }
   export function MyNewWidget({ data, expanded }: WidgetProps) {
     const { foo } = data as MyData;
     if (!foo) return null;   // degrade gracefully — pageData may lack fields
     return <div>...</div>;
   }
   ```
5. **Register** in `frontend/src/widgets/registry.ts`:
   ```ts
   my_new_widget: { component: MyNewWidget, label: '...', description: '...' }
   ```
   For dashboard usage, add `collapsible: true`, `sectionTitle`, and optional
   `summaryComponent` (see Collapsible Components UX in `ui-frontend.md`).
6. **Wire pageData** on the host page: ensure the consuming page's `pageData`
   `useMemo` includes the fields the widget reads.
7. **TDD entity test:** add a case to `backend/tests/Unit/Entity/WidgetDefinitionTest.php`.

## Key Files Reference

| File | Responsibility |
|------|---------------|
| `backend/src/Entity/WidgetDefinition.php` | Catalog entity (one row per type) |
| `backend/src/Entity/PageLayout.php` | Layout per (page, customer) |
| `backend/src/Entity/PageLayoutWidget.php` | Placement row (layout × definition × state × pos) |
| `backend/src/Enum/WidgetType.php` | Allowed widget type strings (16 cases) |
| `backend/src/Enum/PageKey.php` | Allowed page keys (10 cases) |
| `backend/src/Enum/SheetState.php` | `collapsed` / `half` / `full` |
| `backend/src/Controller/Api/WidgetDefinitionApiController.php` | Catalog API (list/patch) |
| `backend/src/Controller/Api/PageLayoutApiController.php` | Admin CRUD for layouts |
| `backend/src/Controller/Api/PageLayoutResolverController.php` | Runtime resolver (customer → global fallback) |
| `backend/src/Repository/PageLayoutRepository.php` | `findForPage()` resolution logic |
| `frontend/src/widgets/registry.ts` | Single frontend source of truth — `WIDGET_REGISTRY` |
| `frontend/src/widgets/types.ts` | `WidgetProps`, `WidgetRegistryMeta` |
| `frontend/src/widgets/*.tsx` | 18 widget components (+ `PlaceholderWidget`) |
| `frontend/src/components/bottom-sheet/WidgetRenderer.tsx` | Renders layout for a sheet state |
| `frontend/src/api/hooks/usePageLayout.ts` | React Query hook (5min staleTime) |
| `frontend/src/types/layout.ts` | `WidgetType`, `PageKey`, `SheetStateName`, `LayoutConfig` |
| `frontend/src/pages/admin/PageLayoutEditorPage.tsx` | Admin editor + live preview |

## Gotchas / Contracts

- **Three-way string contract.** `WidgetType` (PHP) ↔ `WIDGET_REGISTRY` keys (TS)
  ↔ `WidgetType` union (TS). All three must agree by string value. Current
  drift: `customer_kpis`, `customer_optimization` are missing from the PHP enum.
- **`WidgetProps.data` is `unknown`.** Each widget narrows via `as MyData`. The
  page's `pageData` is a loose bag shared across all widgets on that page — no
  per-widget typing. A widget MUST degrade gracefully (`return null` or empty
  state) when its fields are absent, because `PageLayoutEditorPage` previews
  widgets with mock data.
- **`pageData` identity matters.** Wrap in `useMemo` on the host page, since
  `WidgetRenderer` re-creates components on every render.
- **Position ordering.** `PageLayoutApiController::applyWidgets` uses array
  index as `position`; `PageLayout::getWidgetsForState` returns in
  `(sheetState ASC, position ASC)` order (DB-side `#[ORM\OrderBy]`).
- **Unknown widget type.** `WidgetRenderer` silently skips entries where
  `WIDGET_REGISTRY[type]` is undefined. Backend also silently skips on `POST`/`PUT`
  when `WidgetType::tryFrom()` returns null. No error surfaced — check the enum
  first when a widget "disappears" after save.
- **Collapsible wrapping.** Only happens when `mode='page'` AND
  `entry.collapsible === true` AND `entry.sectionTitle` is set. Inside a
  `BottomSheet` (`mode='sheet'`), collapsible wrapping is OFF — the sheet itself
  provides the collapse affordance.
- **Scope fallback.** `PageLayoutResolverController` returns `scope: 'none'`
  with empty widgets when no layout exists — the frontend must handle this
  (empty rendering is fine; `WidgetRenderer` returns null).
- **Admin dashboard layout.** `admin_dashboard` uses `mode='page'` with the
  registry-driven `AdminDashboardPage`, not a `BottomSheet`. See "Registry-Driven
  Dashboard" in `ui-frontend.md`.
