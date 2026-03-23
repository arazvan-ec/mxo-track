# Design Spec: Configurable Widget System for Bottom Sheet Pages

**Date:** 2026-03-23
**Status:** Approved
**Bounded context:** Pragmático (admin tooling / configuration)
**Branch:** `claude/improve-admin-routing-view-3cOqm`
**Depends on:** `2026-03-23-test-routing-bottom-sheet-design.md` (bottom sheet infrastructure)

---

## Summary

Build a configurable widget system that allows admins to define which widgets appear in each bottom sheet state (collapsed/half/full) for each of the 8 map-based pages. Widgets are reusable components registered as `WidgetDefinition` entities. Page layouts (`PageLayout` + `PageLayoutWidget`) define widget placement per state with drag & drop ordering. Supports global defaults with per-customer overrides. Includes a widget gallery with visual previews and a live preview panel in the layout editor. All 8 pages are migrated to consume layouts dynamically.

## Decisions from Brainstorming

| Question | Decision |
|----------|----------|
| Architecture approach | Approach A — Full entity model (WidgetDefinition + PageLayout + PageLayoutWidget) |
| Widget configuration | No individual config — each widget has fixed behavior |
| Preview system | Both: static gallery + live preview in layout editor |
| Global/customer | Global defaults with per-customer override |
| Scope | End-to-end: backend + admin UI + migrate all 8 pages |
| Migration order | By complexity: TestRouting → FleetMap → RoutePlanner → RouteAnalysis → RouteDetail → ShipmentTracking → DriverRoute → CustomerTracking |

---

## Existing Functionality Inventory

### Bottom Sheet Infrastructure (already implemented)

| Element | Location | Description |
|---------|----------|-------------|
| `BottomSheet.tsx` | `frontend/src/components/bottom-sheet/` | Draggable container with 3 states |
| `useBottomSheet.ts` | `frontend/src/components/bottom-sheet/` | State management hook (drag, snap, swipe) |
| `MetricPairs.tsx` | `frontend/src/components/metrics/` | 3-pair metric display |
| `TestRoutingPage.tsx` | `frontend/src/pages/admin/` | Reference implementation with hardcoded widget composition |

### Existing Admin Pages (to be migrated)

| Page | Controller | Current State |
|------|-----------|---------------|
| TestRouting | `TestRoutingController` | Already bottom sheet, hardcoded widgets |
| Fleet Map | `FleetMapController` (TBD) | Sidebar-based or standalone |
| Route Planner | `RoutePlannerController` | React redirect, sidebar layout |
| Route Analysis | `RouteAnalysisController` | React redirect |
| Route Detail | `RouteController` | Twig detail view |
| Shipment Tracking | `ShipmentAdminController` | Twig list/detail |
| Driver Route | `DriverAdminController` | Twig list/detail |
| Customer Tracking | Customer portal | Twig tracking page |

### Admin Widget/Layout System

No existing functionality — this is entirely new.

---

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| BottomSheet component | **Include** | Reuse existing, no changes needed |
| useBottomSheet hook | **Include** | Reuse existing |
| MetricPairs component | **Include** | Becomes one of the 10 widget types |
| TestRoutingPage hardcoded layout | **Transform** | Migrate to dynamic layout from API |
| Existing Twig admin pages | **Transform** | Each migrates to React + map + bottom sheet |
| Widget individual config (JSON schema per widget) | **Omit** | YAGNI — no config per widget in v1, architecture allows adding later |
| Widget permission system (which roles can see which widgets) | **Omit** | Not needed now — pages already have role-based access |
| Widget A/B testing | **Omit** | Over-engineering for current needs |
| Layout versioning/history | **Omit** | Not needed — admin can reconfigure anytime |

---

## Architecture

### Entity Model

```
┌────────────────────┐
│  WidgetDefinition   │
│────────────────────│
│  id (PK bigint)    │
│  public_id (ULID)  │
│  type (WidgetType)  │  ← UNIQUE
│  label (string)     │
│  description (text) │
│  preview_image (str)│  ← path to screenshot for gallery
│  active (bool)      │
│  created_at         │
│  updated_at         │
└────────┬───────────┘
         │ 1
         │
         │ *
┌────────┴───────────┐         ┌──────────────────┐
│  PageLayoutWidget   │ *     1 │    PageLayout     │
│────────────────────│─────────│──────────────────│
│  id (PK bigint)    │         │  id (PK bigint)  │
│  page_layout_id(FK)│         │  public_id (ULID)│
│  widget_def_id(FK) │         │  page_key (enum) │
│  sheet_state (enum)│         │  customer_id(FK) │  ← nullable (null = global)
│  position (int)    │         │  active (bool)   │
│  created_at        │         │  created_at      │
└────────────────────┘         │  updated_at      │
                               └──────────────────┘
                               UNIQUE(page_key, customer_id)
```

### Enums

#### WidgetType (10 types)

```php
enum WidgetType: string
{
    case METRIC_PAIRS = 'metric_pairs';
    case ROUTE_CARD_LIST = 'route_card_list';
    case STOP_LIST = 'stop_list';
    case VEHICLE_INFO = 'vehicle_info';
    case DRIVER_INFO = 'driver_info';
    case SHIPMENT_DETAILS = 'shipment_details';
    case DELIVERY_TIMELINE = 'delivery_timeline';
    case KPI_PILLS = 'kpi_pills';
    case MAP_LEGEND = 'map_legend';
    case ROUTE_COMPARISON = 'route_comparison';
}
```

#### PageKey (8 pages)

```php
enum PageKey: string
{
    case FLEET_MAP = 'fleet_map';
    case TEST_ROUTING = 'test_routing';
    case ROUTE_PLANNER = 'route_planner';
    case ROUTE_ANALYSIS = 'route_analysis';
    case ROUTE_DETAIL = 'route_detail';
    case SHIPMENT_TRACKING = 'shipment_tracking';
    case DRIVER_ROUTE = 'driver_route';
    case CUSTOMER_TRACKING = 'customer_tracking';
}
```

#### SheetState

```php
enum SheetState: string
{
    case COLLAPSED = 'collapsed';
    case HALF = 'half';
    case FULL = 'full';
}
```

### Relationships

- `WidgetDefinition` 1 ← * `PageLayoutWidget` (a widget can appear in many layouts)
- `PageLayout` 1 ← * `PageLayoutWidget` (a layout has many widget placements)
- `PageLayout` * → 1 `Customer` (nullable — global layouts have no customer)
- Same `WidgetDefinition` can appear in multiple states of the same layout (e.g., MetricPairs in collapsed AND half)

### Multi-Tenancy

- `WidgetDefinition` — **NOT tenant-scoped** (global catalog managed by admin)
- `PageLayout` — **tenant-scoped via customer_id** (global defaults + per-customer overrides)
- `PageLayoutWidget` — inherits scope from its `PageLayout`

---

## Admin UI

### 1. Widget Gallery (`/admin/widgets`)

Visual catalog of all available widget types. Purpose: help admins understand what each widget does before placing it in a layout.

```
┌─────────────────────────────────────────────────────┐
│  Widget Gallery                                      │
│                                                      │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐ │
│  │ [screenshot]│  │ [screenshot]│  │ [screenshot]│ │
│  │             │  │             │  │             │ │
│  │ Metric Pairs│  │ Route Cards │  │ Stop List   │ │
│  │ Shows key   │  │ Scrollable  │  │ Ordered list│ │
│  │ metrics in  │  │ route cards │  │ of stops    │ │
│  │ paired      │  │ with stops  │  │ with status │ │
│  │ format      │  │             │  │             │ │
│  │ ● Active    │  │ ● Active    │  │ ● Active    │ │
│  └─────────────┘  └─────────────┘  └─────────────┘ │
│                                                      │
│  ┌─────────────┐  ┌─────────────┐  ...              │
│  │ [screenshot]│  │ [screenshot]│                    │
│  │ Vehicle Info│  │ Driver Info │                    │
│  │ ...         │  │ ...         │                    │
│  └─────────────┘  └─────────────┘                    │
└─────────────────────────────────────────────────────┘
```

Each card shows:
- Preview image (screenshot of the widget rendered with sample data)
- Live preview (actual React component rendered with mock data)
- Widget name and description
- Active/inactive toggle

### 2. Page Layout Editor (`/admin/page-layouts`)

Configure which widgets appear in each bottom sheet state for a given page.

```
┌──────────────────────────────────────────────────────────────────────┐
│  Page Layout Editor                                                   │
│                                                                       │
│  Page: [TestRouting ▼]   Scope: [● Global  ○ Customer: _____ ]       │
│                                                                       │
│  ┌─ Available Widgets ──┐                                             │
│  │ ○ Metric Pairs       │                                             │
│  │ ○ Route Card List    │                                             │
│  │ ○ Stop List          │                                             │
│  │ ○ Vehicle Info       │                                             │
│  │ ○ KPI Pills          │                                             │
│  │ ...                  │                                             │
│  └──────────────────────┘                                             │
│                                                                       │
│  ┌─ Collapsed (15%) ─┐  ┌─ Half (50%) ──────┐  ┌─ Full (85%) ─────┐ │
│  │                    │  │                    │  │                    │ │
│  │ 1. Metric Pairs  ↕ │  │ 1. Metric Pairs  ↕ │  │ 1. Metric Pairs  ↕│
│  │                    │  │ 2. Route Cards   ↕ │  │ 2. Route Cards   ↕│
│  │                    │  │                    │  │ 3. Stop List     ↕│
│  │                    │  │                    │  │ 4. Map Legend    ↕│
│  └────────────────────┘  └────────────────────┘  └────────────────────┘│
│                                                                       │
│  ┌─ Live Preview ────────────────────────────────────────────────────┐│
│  │                                                                    ││
│  │  ┌─ Simulated Bottom Sheet ──────────────────────────────────┐    ││
│  │  │  ≡ Test Routing Results                                    │    ││
│  │  │                                                            │    ││
│  │  │  ┌──────────┐  ┌──────────────┐  ┌──────────┐            │    ││
│  │  │  │ 2 rutas  │  │ 45.3 km      │  │ 1h 23m   │            │    ││
│  │  │  │ 10 stops │  │ ▼ 28.6%      │  │ ▼ 15.2%  │            │    ││
│  │  │  └──────────┘  └──────────────┘  └──────────┘            │    ││
│  │  └────────────────────────────────────────────────────────────┘    ││
│  │                                                                    ││
│  │  State: [Collapsed] [Half] [Full]                                  ││
│  └────────────────────────────────────────────────────────────────────┘│
│                                                                       │
│  [Save Layout]  [Reset to Default]                                    │
└──────────────────────────────────────────────────────────────────────┘
```

Features:
- Drag widgets from "Available" to any state column
- Drag to reorder within a state (↕ handles)
- Remove widget by dragging out or clicking ✕
- Live preview updates immediately on any change
- Preview has state tabs (collapsed/half/full) to see each state
- Preview renders actual React widget components with mock data
- "Reset to Default" restores global layout (only on customer overrides)

---

## API

### Layout Resolution Endpoint

`GET /api/page-layouts/{pageKey}`

Resolves the effective layout for the authenticated user:
1. If user's customer has an active override for this page → use it
2. Else → use the global default (customer_id = null)
3. If no layout exists → return empty (frontend falls back to hardcoded defaults)

**Response:**

```json
{
  "pageKey": "test_routing",
  "scope": "global",
  "widgets": {
    "collapsed": [
      {"type": "metric_pairs", "position": 0}
    ],
    "half": [
      {"type": "metric_pairs", "position": 0},
      {"type": "route_card_list", "position": 1}
    ],
    "full": [
      {"type": "metric_pairs", "position": 0},
      {"type": "route_card_list", "position": 1},
      {"type": "stop_list", "position": 2},
      {"type": "map_legend", "position": 3}
    ]
  }
}
```

**Auth:** Any authenticated user (layout is resolved per-tenant)

### Admin CRUD Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/admin/api/widgets` | List all widget definitions |
| PATCH | `/admin/api/widgets/{publicId}` | Toggle active/inactive |
| GET | `/admin/api/page-layouts` | List all layouts (filterable by pageKey, customerId) |
| GET | `/admin/api/page-layouts/{publicId}` | Get layout with widgets |
| POST | `/admin/api/page-layouts` | Create layout |
| PUT | `/admin/api/page-layouts/{publicId}` | Update layout (replaces all widget placements) |
| DELETE | `/admin/api/page-layouts/{publicId}` | Delete layout |

**Auth:** ROLE_ADMIN only

---

## Frontend Architecture

### Widget Registry (React)

```typescript
// frontend/src/widgets/registry.ts

interface WidgetRegistryEntry {
  type: WidgetType;
  component: React.ComponentType<WidgetProps>;
  previewComponent: React.ComponentType;  // renders with mock data
  label: string;
  description: string;
}

const WIDGET_REGISTRY: Record<WidgetType, WidgetRegistryEntry> = {
  metric_pairs: {
    type: 'metric_pairs',
    component: MetricPairsWidget,
    previewComponent: MetricPairsPreview,
    label: 'Metric Pairs',
    description: 'Key metrics in paired format (scope, distance, time)',
  },
  // ... 9 more
};
```

### Dynamic Widget Renderer

```typescript
// frontend/src/components/bottom-sheet/WidgetRenderer.tsx

interface WidgetRendererProps {
  layout: LayoutConfig;
  sheetState: SheetState;
  pageData: unknown;  // page-specific data passed to widgets
}

function WidgetRenderer({ layout, sheetState, pageData }: WidgetRendererProps) {
  const widgets = layout.widgets[sheetState] ?? [];

  return (
    <>
      {widgets.map(({ type, position }) => {
        const entry = WIDGET_REGISTRY[type];
        if (!entry) return null;
        const Component = entry.component;
        return <Component key={`${type}-${position}`} data={pageData} />;
      })}
    </>
  );
}
```

### Page Template (after migration)

Each page follows this pattern:

```typescript
function SomePage() {
  const { data, loading, error } = usePageData();
  const { layout } = usePageLayout('some_page_key');
  const { sheetState, ...sheetProps } = useBottomSheet();

  return (
    <div className="relative h-screen">
      <TopBar />
      <MapCanvas ...>
        {/* Map layers specific to this page */}
      </MapCanvas>
      <BottomSheet title="Page Title" {...sheetProps}>
        <WidgetRenderer
          layout={layout}
          sheetState={sheetState}
          pageData={data}
        />
      </BottomSheet>
    </div>
  );
}
```

### usePageLayout Hook

```typescript
// frontend/src/hooks/usePageLayout.ts

function usePageLayout(pageKey: PageKey): {
  layout: LayoutConfig;
  loading: boolean;
  error: string | null;
} {
  // Fetches GET /api/page-layouts/{pageKey}
  // Caches in memory for the session
  // Returns fallback empty layout while loading
}
```

---

## Widget Components (10 types)

Each widget is a React component that receives page-specific data and renders its visualization.

| Widget Type | What it shows | Typical pages |
|------------|---------------|---------------|
| `MetricPairs` | 3 pairs of hero metric + delta badge | TestRouting, RouteAnalysis, FleetMap |
| `RouteCardList` | Scrollable list of route cards with stops | TestRouting, RoutePlanner, RouteAnalysis |
| `StopList` | Ordered list of stops with status indicators | RouteDetail, DriverRoute |
| `VehicleInfo` | Vehicle details panel (plate, model, capacity) | FleetMap, RouteDetail |
| `DriverInfo` | Driver details panel (name, phone, status) | FleetMap, DriverRoute |
| `ShipmentDetails` | Shipment info (recipient, address, status) | ShipmentTracking, CustomerTracking |
| `DeliveryTimeline` | Vertical timeline of delivery events | ShipmentTracking, CustomerTracking, RouteDetail |
| `KpiPills` | Compact KPI pills (on-time %, delivered, pending) | FleetMap, RouteAnalysis |
| `MapLegend` | Map legend showing route colors and markers | TestRouting, FleetMap, RoutePlanner |
| `RouteComparison` | Before/after comparison (original vs optimized) | TestRouting, RouteAnalysis |

---

## Data Flow

### Widget Data Contract

Each page provides data to widgets through a generic `data` prop. Widgets extract what they need:

```typescript
// Each widget type defines what it needs from data
interface MetricPairsData {
  metrics: {
    routeCount: number;
    stopCount: number;
    distanceBeforeKm: number;
    distanceAfterKm: number;
    savedPercent: number;
    durationBeforeMinutes: number;
    totalDurationMinutes: number;
    timeSavedPercent: number;
  };
}

// Widget component casts/validates data
function MetricPairsWidget({ data }: WidgetProps) {
  const { metrics } = data as MetricPairsData;
  // render...
}
```

### Layout Resolution Flow

```
User opens page
  → Frontend calls GET /api/page-layouts/{pageKey}
    → Backend checks: customer override exists?
      → YES: return customer layout
      → NO: return global layout
      → NEITHER: return empty (frontend uses hardcoded fallback)
  → WidgetRenderer maps layout to components
  → Each component renders with page data
```

---

## Database Seeding

Initial migration should seed:
1. All 10 `WidgetDefinition` entities (one per WidgetType)
2. Global default `PageLayout` for each of the 8 pages with sensible widget placements

### Default Layouts

| Page | Collapsed | Half | Full |
|------|-----------|------|------|
| TestRouting | MetricPairs | MetricPairs, RouteCardList | MetricPairs, RouteCardList, RouteComparison, MapLegend |
| FleetMap | KpiPills | KpiPills, VehicleInfo | KpiPills, VehicleInfo, DriverInfo, MapLegend |
| RoutePlanner | MetricPairs | MetricPairs, RouteCardList | MetricPairs, RouteCardList, StopList, MapLegend |
| RouteAnalysis | MetricPairs | MetricPairs, RouteComparison | MetricPairs, RouteComparison, RouteCardList, KpiPills |
| RouteDetail | MetricPairs | MetricPairs, StopList | MetricPairs, StopList, DeliveryTimeline, VehicleInfo |
| ShipmentTracking | ShipmentDetails | ShipmentDetails, DeliveryTimeline | ShipmentDetails, DeliveryTimeline, MapLegend |
| DriverRoute | MetricPairs | MetricPairs, StopList | MetricPairs, StopList, DeliveryTimeline, DriverInfo |
| CustomerTracking | ShipmentDetails | ShipmentDetails, DeliveryTimeline | ShipmentDetails, DeliveryTimeline, MapLegend |

---

## Migration & Implementation Order

All 8 pages are migrated in order of complexity. Each migration:
1. Creates the page's React component with map + bottom sheet
2. Adds necessary API endpoints for page data
3. Connects to `usePageLayout` for dynamic widget rendering
4. Seeds the default global layout

### Order

1. **TestRouting** — Adapt existing implementation to use dynamic layouts (simplest: already has bottom sheet)
2. **FleetMap** — Live vehicle tracking map (reuse existing fleet components)
3. **RoutePlanner** — Route creation/planning with map
4. **RouteAnalysis** — Post-route analysis with comparison
5. **RouteDetail** — Single route detail with stops on map
6. **ShipmentTracking** — Shipment tracking with delivery events
7. **DriverRoute** — Driver's route view with navigation
8. **CustomerTracking** — Customer-facing shipment tracking portal

---

## Edge Cases

1. **No layout configured** — Frontend falls back to hardcoded default (same as current)
2. **Widget definition deactivated** — WidgetRenderer skips inactive widgets
3. **Customer override deleted** — Falls back to global layout
4. **Empty state (collapsed with no widgets)** — Show only drag handle with page title
5. **Widget type not in registry** — WidgetRenderer skips unknown types (forward compatible)
6. **Same widget in multiple states** — Allowed (e.g., MetricPairs in collapsed AND half AND full)
7. **Admin edits layout while user is viewing** — No live update needed; next page load picks up changes
8. **Customer has no override** — Uses global; admin UI shows "Using global default" indicator

---

## File Structure (new files)

```
backend/
├── src/
│   ├── Entity/
│   │   ├── WidgetDefinition.php
│   │   ├── PageLayout.php
│   │   └── PageLayoutWidget.php
│   ├── Enum/
│   │   ├── WidgetType.php
│   │   ├── PageKey.php
│   │   └── SheetState.php
│   ├── Repository/
│   │   ├── WidgetDefinitionRepository.php
│   │   ├── PageLayoutRepository.php
│   │   └── PageLayoutWidgetRepository.php
│   ├── Controller/Admin/
│   │   ├── WidgetGalleryController.php      (Twig page hosting React)
│   │   └── PageLayoutEditorController.php   (Twig page hosting React)
│   ├── Controller/Api/Admin/
│   │   ├── WidgetDefinitionApiController.php
│   │   └── PageLayoutApiController.php
│   └── Controller/Api/
│       └── PageLayoutResolverController.php  (public layout resolution)
│
frontend/
├── src/
│   ├── widgets/
│   │   ├── registry.ts                       (widget type → component mapping)
│   │   ├── types.ts                          (shared widget interfaces)
│   │   ├── MetricPairsWidget.tsx
│   │   ├── RouteCardListWidget.tsx
│   │   ├── StopListWidget.tsx
│   │   ├── VehicleInfoWidget.tsx
│   │   ├── DriverInfoWidget.tsx
│   │   ├── ShipmentDetailsWidget.tsx
│   │   ├── DeliveryTimelineWidget.tsx
│   │   ├── KpiPillsWidget.tsx
│   │   ├── MapLegendWidget.tsx
│   │   ├── RouteComparisonWidget.tsx
│   │   └── previews/                         (mock-data preview components)
│   │       ├── MetricPairsPreview.tsx
│   │       └── ... (one per widget)
│   ├── components/bottom-sheet/
│   │   └── WidgetRenderer.tsx                (dynamic widget rendering)
│   ├── hooks/
│   │   └── usePageLayout.ts                  (layout resolution hook)
│   ├── pages/admin/
│   │   ├── WidgetGalleryPage.tsx
│   │   ├── PageLayoutEditorPage.tsx
│   │   ├── TestRoutingPage.tsx               (adapt to dynamic layout)
│   │   ├── FleetMapPage.tsx
│   │   ├── RoutePlannerPage.tsx
│   │   ├── RouteAnalysisPage.tsx
│   │   ├── RouteDetailPage.tsx
│   │   ├── ShipmentTrackingPage.tsx
│   │   ├── DriverRoutePage.tsx
│   │   └── CustomerTrackingPage.tsx
│   └── types/
│       └── layout.ts                         (LayoutConfig, WidgetPlacement types)
```

---

## Non-Goals (explicit)

- **Widget marketplace** — No third-party widgets. Only the 10 built-in types.
- **Widget config** — No per-placement configuration in v1.
- **Live sync** — Layout changes don't push to active sessions.
- **Analytics** — No tracking of widget usage/engagement.
- **Responsive breakpoints** — Bottom sheet is mobile-first; desktop uses same layout.
- **Widget permissions** — No per-widget role restrictions (pages handle access).
