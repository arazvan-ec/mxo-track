# Implementation Plan: Configurable Widget System

**Goal:** Build a configurable widget system for bottom sheet pages — backend entities + enums + API + frontend widget registry + renderer + admin UI (gallery + layout editor) + migrate TestRoutingPage to dynamic layouts.

**Spec:** `docs/superpowers/specs/admin-routing-view/2026-03-23-widget-system-design.md`
**Branch:** `claude/improve-admin-routing-view-3cOqm`

**Architecture:** Pragmatic Symfony (entities in `src/Entity/`, controllers in `src/Controller/`)

**Scope for this plan:** Foundation + TestRouting migration only. Other 7 pages are separate future plans.

---

## File Structure

```
backend/
├── src/
│   ├── Entity/
│   │   ├── WidgetDefinition.php          (NEW)
│   │   ├── PageLayout.php                (NEW)
│   │   └── PageLayoutWidget.php          (NEW)
│   ├── Enum/
│   │   ├── WidgetType.php                (NEW)
│   │   ├── PageKey.php                   (NEW)
│   │   └── SheetState.php                (NEW)
│   ├── Repository/
│   │   ├── WidgetDefinitionRepository.php (NEW)
│   │   ├── PageLayoutRepository.php       (NEW)
│   │   └── PageLayoutWidgetRepository.php (NEW)
│   ├── Controller/Api/
│   │   ├── PageLayoutResolverController.php (NEW)
│   │   ├── WidgetDefinitionApiController.php (NEW)
│   │   └── PageLayoutApiController.php      (NEW)
│   └── Controller/Admin/
│       ├── WidgetGalleryController.php      (NEW)
│       └── PageLayoutEditorController.php   (NEW)
├── templates/admin/
│   ├── widget-gallery/index.html.twig       (NEW)
│   └── page-layout-editor/index.html.twig   (NEW)
├── migrations/
│   └── Version20260323XXXXXX.php            (NEW — schema + seed data)

frontend/
├── src/
│   ├── types/
│   │   └── layout.ts                       (NEW)
│   ├── widgets/
│   │   ├── types.ts                        (NEW)
│   │   ├── registry.ts                     (NEW)
│   │   ├── MetricPairsWidget.tsx           (NEW)
│   │   ├── RouteCardListWidget.tsx         (NEW)
│   │   ├── MapLegendWidget.tsx             (NEW)
│   │   ├── RouteComparisonWidget.tsx       (NEW)
│   │   └── PlaceholderWidget.tsx           (NEW — for widget types not yet implemented)
│   ├── components/bottom-sheet/
│   │   └── WidgetRenderer.tsx              (NEW)
│   ├── api/hooks/
│   │   └── usePageLayout.ts               (NEW)
│   ├── pages/admin/
│   │   ├── TestRoutingPage.tsx             (MODIFY — use WidgetRenderer)
│   │   ├── WidgetGalleryPage.tsx           (NEW)
│   │   └── PageLayoutEditorPage.tsx        (NEW)
│   └── router.tsx                          (MODIFY — add gallery + editor routes)
```

---

## Tasks

### Phase 1: Backend Enums (5 min)

- [ ] **Task 1.1:** Create `WidgetType` enum
  - File: `backend/src/Enum/WidgetType.php`
  - 10 cases: `metric_pairs`, `route_card_list`, `stop_list`, `vehicle_info`, `driver_info`, `shipment_details`, `delivery_timeline`, `kpi_pills`, `map_legend`, `route_comparison`
  - Follow pattern of `RouteStatus.php` — backed string enum

- [ ] **Task 1.2:** Create `PageKey` enum
  - File: `backend/src/Enum/PageKey.php`
  - 8 cases: `fleet_map`, `test_routing`, `route_planner`, `route_analysis`, `route_detail`, `shipment_tracking`, `driver_route`, `customer_tracking`

- [ ] **Task 1.3:** Create `SheetState` enum
  - File: `backend/src/Enum/SheetState.php`
  - 3 cases: `collapsed`, `half`, `full`

- [ ] **Commit:** `feat: add WidgetType, PageKey, and SheetState enums`

### Phase 2: Backend Entities (10 min)

- [ ] **Task 2.1:** Create `WidgetDefinition` entity
  - File: `backend/src/Entity/WidgetDefinition.php`
  - Uses `PublicIdTrait`, `#[ORM\HasLifecycleCallbacks]`
  - Fields: `type` (WidgetType, unique), `label` (string 120), `description` (text nullable), `previewImage` (string 255 nullable), `active` (bool default true), `createdAt`, `updatedAt`
  - Constructor: `__construct(WidgetType $type, string $label)`
  - Lifecycle: `#[ORM\PrePersist]` touchCreatedAt, `#[ORM\PreUpdate]` touchUpdatedAt
  - NOT CustomerScoped (global catalog)

- [ ] **Task 2.2:** Create `PageLayout` entity
  - File: `backend/src/Entity/PageLayout.php`
  - Uses `PublicIdTrait`, `#[ORM\HasLifecycleCallbacks]`
  - Fields: `pageKey` (PageKey enum), `customer` (ManyToOne Customer, nullable), `active` (bool default true), `createdAt`, `updatedAt`
  - Relationship: `OneToMany` to `PageLayoutWidget` (cascade persist+remove, orphanRemoval)
  - UniqueConstraint: `(page_key, customer_id)` — one layout per page per customer (null = global)
  - Constructor: `__construct(PageKey $pageKey, ?Customer $customer = null)`
  - Methods: `addWidget(PageLayoutWidget)`, `removeWidget(PageLayoutWidget)`, `clearWidgets()`, `getWidgetsForState(SheetState): Collection`

- [ ] **Task 2.3:** Create `PageLayoutWidget` entity
  - File: `backend/src/Entity/PageLayoutWidget.php`
  - Uses `PublicIdTrait`, `#[ORM\HasLifecycleCallbacks]`
  - Fields: `pageLayout` (ManyToOne PageLayout), `widgetDefinition` (ManyToOne WidgetDefinition), `sheetState` (SheetState enum), `position` (int), `createdAt`
  - Constructor: `__construct(PageLayout $layout, WidgetDefinition $widget, SheetState $state, int $position)`
  - Index on `(page_layout_id, sheet_state, position)` for ordered queries

- [ ] **Commit:** `feat: add WidgetDefinition, PageLayout, and PageLayoutWidget entities`

### Phase 3: Backend Repositories (5 min)

- [ ] **Task 3.1:** Create `WidgetDefinitionRepository`
  - File: `backend/src/Repository/WidgetDefinitionRepository.php`
  - Extends `ServiceEntityRepository<WidgetDefinition>`
  - Methods: `findOneByPublicId(string): ?WidgetDefinition`, `findByType(WidgetType): ?WidgetDefinition`, `findAllActive(): array`

- [ ] **Task 3.2:** Create `PageLayoutRepository`
  - File: `backend/src/Repository/PageLayoutRepository.php`
  - Extends `ServiceEntityRepository<PageLayout>`
  - Methods: `findOneByPublicId(string): ?PageLayout`, `findForPage(PageKey, ?Customer): ?PageLayout` (resolves: customer override → global fallback), `findAllByPage(PageKey): array`

- [ ] **Task 3.3:** Create `PageLayoutWidgetRepository`
  - File: `backend/src/Repository/PageLayoutWidgetRepository.php`
  - Extends `ServiceEntityRepository<PageLayoutWidget>`
  - Minimal — most queries go through `PageLayout` relationship

- [ ] **Commit:** `feat: add widget system repositories`

### Phase 4: Database Migration with Seed Data (10 min)

- [ ] **Task 4.1:** Generate and customize migration
  - Run: `php bin/console doctrine:migrations:diff` to auto-generate
  - Verify: 3 tables (widget_definition, page_layout, page_layout_widget), indexes, constraints
  - Add seed SQL to `up()`: INSERT all 10 WidgetDefinitions + 8 global PageLayouts + PageLayoutWidgets per spec default layouts table

  Seed SQL pattern:
  ```sql
  -- Widget definitions (10 rows)
  INSERT INTO widget_definition (public_id, type, label, description, active, created_at, updated_at) VALUES
  (gen_random_uuid()::text, 'metric_pairs', 'Metric Pairs', 'Key metrics in paired format', true, NOW(), NOW()),
  ...;

  -- Global page layouts (8 rows, customer_id = NULL)
  INSERT INTO page_layout (public_id, page_key, customer_id, active, created_at, updated_at) VALUES
  (gen_random_uuid()::text, 'test_routing', NULL, true, NOW(), NOW()),
  ...;

  -- Page layout widgets (per default layouts table in spec)
  -- TestRouting collapsed: metric_pairs@0
  -- TestRouting half: metric_pairs@0, route_card_list@1
  -- TestRouting full: metric_pairs@0, route_card_list@1, route_comparison@2, map_legend@3
  -- (repeat for all 8 pages)
  ```

- [ ] **Task 4.2:** Run migration
  - Run: `php bin/console doctrine:migrations:migrate -n`
  - Verify: `php bin/console dbal:run-sql "SELECT count(*) FROM widget_definition"` → 10
  - Verify: `php bin/console dbal:run-sql "SELECT count(*) FROM page_layout"` → 8

- [ ] **Commit:** `feat: add widget system migration with seed data`

### Phase 5: Backend API — Layout Resolution (5 min)

- [ ] **Task 5.1:** Create `PageLayoutResolverController`
  - File: `backend/src/Controller/Api/PageLayoutResolverController.php`
  - Route: `GET /api/page-layouts/{pageKey}` — any authenticated user
  - Logic:
    1. Parse `pageKey` to `PageKey` enum (404 if invalid)
    2. Get current user's customer (null for admin/operator)
    3. Call `PageLayoutRepository::findForPage(pageKey, customer)`
    4. Return JSON: `{ pageKey, scope: "global"|"customer", widgets: { collapsed: [...], half: [...], full: [...] } }`
    5. If no layout: return `{ pageKey, scope: "none", widgets: { collapsed: [], half: [], full: [] } }`

- [ ] **Commit:** `feat: add page layout resolver API endpoint`

### Phase 6: Backend API — Admin CRUD (15 min)

- [ ] **Task 6.1:** Create `WidgetDefinitionApiController`
  - File: `backend/src/Controller/Api/WidgetDefinitionApiController.php`
  - `#[IsGranted('ROLE_ADMIN')]`
  - `GET /api/admin/widgets` — list all widget definitions
  - `PATCH /api/admin/widgets/{publicId}` — toggle active/inactive (JSON body: `{ active: bool }`)

- [ ] **Task 6.2:** Create `PageLayoutApiController`
  - File: `backend/src/Controller/Api/PageLayoutApiController.php`
  - `#[IsGranted('ROLE_ADMIN')]`
  - `GET /api/admin/page-layouts` — list all layouts (optional `?pageKey=X&customerId=Y` filters)
  - `GET /api/admin/page-layouts/{publicId}` — get layout with all widgets
  - `POST /api/admin/page-layouts` — create layout (body: `{ pageKey, customerId?, widgets: { collapsed: [...], half: [...], full: [...] } }`)
  - `PUT /api/admin/page-layouts/{publicId}` — replace layout widgets (same body as POST minus pageKey/customerId)
  - `DELETE /api/admin/page-layouts/{publicId}` — delete layout

  PUT logic (replace all widgets):
  1. Call `$layout->clearWidgets()`
  2. For each state in body.widgets, for each widget type+position:
     - Find WidgetDefinition by type
     - Create new PageLayoutWidget
     - Add to layout
  3. Flush

- [ ] **Commit:** `feat: add admin CRUD API for widgets and page layouts`

### Phase 7: Frontend Types & Layout Hook (5 min)

- [ ] **Task 7.1:** Create layout types
  - File: `frontend/src/types/layout.ts`
  ```typescript
  export type WidgetType = 'metric_pairs' | 'route_card_list' | 'stop_list' | 'vehicle_info' | 'driver_info' | 'shipment_details' | 'delivery_timeline' | 'kpi_pills' | 'map_legend' | 'route_comparison';
  export type SheetState = 'collapsed' | 'half' | 'full';
  export type PageKey = 'fleet_map' | 'test_routing' | 'route_planner' | 'route_analysis' | 'route_detail' | 'shipment_tracking' | 'driver_route' | 'customer_tracking';

  export interface WidgetPlacement {
    type: WidgetType;
    position: number;
  }

  export interface LayoutConfig {
    pageKey: PageKey;
    scope: 'global' | 'customer' | 'none';
    widgets: Record<SheetState, WidgetPlacement[]>;
  }
  ```

- [ ] **Task 7.2:** Create `usePageLayout` hook
  - File: `frontend/src/api/hooks/usePageLayout.ts`
  - Uses `@tanstack/react-query` like `useTestRoutingData`
  - Fetches `GET /api/page-layouts/{pageKey}`
  - `staleTime: 5 * 60 * 1000` (5 min cache)
  - Returns `{ layout: LayoutConfig, isLoading, error }`
  - Fallback when loading: empty layout `{ widgets: { collapsed: [], half: [], full: [] } }`

- [ ] **Commit:** `feat: add layout types and usePageLayout hook`

### Phase 8: Frontend Widget Components (15 min)

- [ ] **Task 8.1:** Create widget types
  - File: `frontend/src/widgets/types.ts`
  ```typescript
  export interface WidgetProps {
    data: unknown;
    expanded?: boolean;  // true when sheet is not collapsed
  }
  ```

- [ ] **Task 8.2:** Create `MetricPairsWidget`
  - File: `frontend/src/widgets/MetricPairsWidget.tsx`
  - Wraps existing `MetricPairs` component
  - Extracts `metrics` from `data` prop (cast to `{ metrics: TestRoutingMetrics }`)
  - Passes `expanded` through

- [ ] **Task 8.3:** Create `RouteCardListWidget`
  - File: `frontend/src/widgets/RouteCardListWidget.tsx`
  - Extracts `routesData` from data
  - Renders the route cards list (extract `RouteCard` from TestRoutingPage into shared component or inline here)
  - Receives `onRouteSelect`, `highlightedRouteIdx` via data

- [ ] **Task 8.4:** Create `MapLegendWidget`
  - File: `frontend/src/widgets/MapLegendWidget.tsx`
  - Renders route color legend
  - Extracts route names and colors from data

- [ ] **Task 8.5:** Create `RouteComparisonWidget`
  - File: `frontend/src/widgets/RouteComparisonWidget.tsx`
  - Before/after comparison summary
  - Simple table showing original vs optimized totals

- [ ] **Task 8.6:** Create `PlaceholderWidget`
  - File: `frontend/src/widgets/PlaceholderWidget.tsx`
  - Used for widget types not yet implemented (StopList, VehicleInfo, etc.)
  - Shows widget type name + "Coming soon" message with a dashed border

- [ ] **Commit:** `feat: add widget components for TestRouting page`

### Phase 9: Widget Registry & Renderer (5 min)

- [ ] **Task 9.1:** Create widget registry
  - File: `frontend/src/widgets/registry.ts`
  - Maps `WidgetType` → `{ component, label, description }`
  - Implemented widgets use their component; unimplemented use `PlaceholderWidget`

- [ ] **Task 9.2:** Create `WidgetRenderer`
  - File: `frontend/src/components/bottom-sheet/WidgetRenderer.tsx`
  - Props: `layout: LayoutConfig`, `sheetState: SheetState` (from BottomSheet), `pageData: unknown`
  - Looks up widgets for current state from layout
  - Renders each via registry lookup
  - Skips unknown types silently

- [ ] **Commit:** `feat: add widget registry and WidgetRenderer component`

### Phase 10: Migrate TestRoutingPage (10 min)

- [ ] **Task 10.1:** Modify `TestRoutingPage` to use dynamic layout
  - File: `frontend/src/pages/admin/TestRoutingPage.tsx`
  - Add `usePageLayout('test_routing')` call
  - Replace hardcoded `<MetricPairs>` + route cards with `<WidgetRenderer>`
  - Keep map layers, legend (overlaid on map, not in bottom sheet), and interaction logic
  - Pass `{ metrics, routesData, highlightedRouteIdx, onRouteSelect }` as `pageData`
  - Extract `RouteCard` + helper components out of the file into `frontend/src/components/routes/RouteCard.tsx` for reuse

- [ ] **Commit:** `feat: migrate TestRoutingPage to dynamic widget layout`

### Phase 11: Admin UI — Widget Gallery & Layout Editor Pages (20 min)

- [ ] **Task 11.1:** Create `WidgetGalleryController` (Twig host)
  - File: `backend/src/Controller/Admin/WidgetGalleryController.php`
  - Route: `#[Route('/admin/widgets', name: 'admin_widget_gallery')]`
  - `#[IsGranted('ROLE_ADMIN')]`
  - Renders `admin/widget-gallery/index.html.twig` (React entry point)

- [ ] **Task 11.2:** Create `PageLayoutEditorController` (Twig host)
  - File: `backend/src/Controller/Admin/PageLayoutEditorController.php`
  - Route: `#[Route('/admin/page-layouts', name: 'admin_page_layout_editor')]`
  - `#[IsGranted('ROLE_ADMIN')]`
  - Renders `admin/page-layout-editor/index.html.twig` (React entry point)

- [ ] **Task 11.3:** Create Twig templates for both pages
  - Files: `backend/templates/admin/widget-gallery/index.html.twig`, `backend/templates/admin/page-layout-editor/index.html.twig`
  - Follow existing pattern of hosting React SPA

- [ ] **Task 11.4:** Create `WidgetGalleryPage` React component
  - File: `frontend/src/pages/admin/WidgetGalleryPage.tsx`
  - Fetches `GET /api/admin/widgets`
  - Grid of cards with widget name, description, preview (rendered component with mock data)
  - Active/inactive toggle per widget (PATCH call)

- [ ] **Task 11.5:** Create `PageLayoutEditorPage` React component
  - File: `frontend/src/pages/admin/PageLayoutEditorPage.tsx`
  - Page selector dropdown (PageKey enum)
  - Scope selector (Global / Customer)
  - Three columns for collapsed/half/full states
  - Available widgets sidebar
  - Drag & drop from sidebar → columns (use HTML5 drag or simple click-to-add)
  - Click X to remove widget from state
  - Position reorder with up/down buttons (simpler than full drag reorder for v1)
  - Save button → PUT /api/admin/page-layouts/{publicId}
  - Live preview panel: renders WidgetRenderer with mock data, state tab switcher

- [ ] **Task 11.6:** Add routes to `router.tsx`
  - Add `admin/widgets` → `WidgetGalleryPage`
  - Add `admin/page-layouts` → `PageLayoutEditorPage`

- [ ] **Commit:** `feat: add Widget Gallery and Page Layout Editor admin UI`

### Phase 12: Navigation Integration (5 min)

- [ ] **Task 12.1:** Add widget gallery and layout editor to admin navigation
  - Find sidebar/navigation component and add links
  - File: likely `backend/src/Controller/Api/NavigationController.php` or `frontend/src/components/layout/NavigationSidebar.tsx`
  - Add under a "Configuration" or "Settings" section

- [ ] **Commit:** `feat: add widget system links to admin navigation`

### Phase 13: Verification & Cleanup (5 min)

- [ ] **Task 13.1:** Run full test suite
  - `cd backend && php vendor/bin/phpunit`
  - `cd frontend && npm run build` (verify no TS errors)

- [ ] **Task 13.2:** Run linter
  - `cd backend && make lint`

- [ ] **Task 13.3:** Update `codebase-manifest.md`
  - `make manifest`

- [ ] **Commit:** `chore: update codebase manifest`

---

## Risk Assessment

| Risk | Mitigation |
|------|-----------|
| Migration seed data SQL is complex | Generate ULIDs in PHP fixture instead of raw SQL if needed |
| Drag & drop in layout editor is complex | v1 uses click-to-add + up/down buttons; full DnD in v2 |
| Widget data contract coupling | Each widget casts `data` to its expected shape; no compile-time safety but runtime-safe with null checks |
| Pre-existing test failures | Document; don't let them block this work |

## Estimated Complexity: L (Large)

- 3 new entities, 3 enums, 3 repositories
- 3 new API controllers (7 endpoints)
- 2 new Twig controllers + templates
- 6+ new React components + registry + hook
- 1 migration with seed data
- 1 page migration (TestRouting)
