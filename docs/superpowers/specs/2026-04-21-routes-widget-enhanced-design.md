---
type: spec
feature: routes-widget-enhanced
date: 2026-04-21
branch: claude/enhance-routes-widget-8UzuC
related_log: docs/superpowers/execution-logs/2026-04-14-expand-routes-card.md
---

# Spec — Routes Widget Enhanced (Dashboard)

## Context

The admin dashboard's "Rutas activas" expandable KPI card (built in PR #253,
`2026-04-14-expand-routes-card.md`) currently shows only name · driver · vehicle ·
stops progress for each active route. The user requested richer information
aligned with the stated business value: *the business sells saved kilometers and
saved time*.

This spec covers 4 enhancements agreed with the user:

1. **Route optimization metrics** — distance + estimated duration
2. **Next pending stop** — address, recipient, committed delivery window
3. **Load context** — total weight and parcel count
4. **Daily progress sparkline** — hourly histogram of deliveries

## Bounded Context

**Pragmatic (Symfony).** The work extends a read-only DTO projection in the
existing `RouteListApiController`. No new abstractions, no modifications to the
`Route` aggregate, no new coupling to domain models. The subquery pattern
mirrors the existing `stopCounts` aggregation at
`backend/src/Controller/Api/Admin/RouteListApiController.php:74-92`.

## Existing Functionality Inventory

| Element | Source | Currently rendered? | Decision |
|---|---|---|---|
| `Route.name` | domain | Yes | Include |
| `Route.publicId` | domain | Yes (key) | Include |
| `Route.customerName` | via `->getCustomer()?->getName()` | **No** (in DTO, not shown) | **Include** — render for multi-tenant awareness |
| `Route.vehicleName` | via `->getVehicle()?->getName()` | Yes | Include |
| `Route.driverName` / `driverEmail` | via `->getDriver()` | Yes | Include |
| `Route.status` | domain | No (filter only) | Keep omitted |
| `deliveredStops` / `totalStops` | subquery on `RouteStop.status` | Yes (progress bar) | Include |
| `Route.totalDistanceKm` | domain (nullable string→float) | No | **Include** — core business value |
| `Route.estimatedDurationMinutes` | domain (nullable int) | No | **Include** — core business value |
| `Route.totalWeightKg` | domain (nullable string→float) | No | **Include** — load context |
| `Route.totalParcels` | domain (nullable int) | No | **Include** — load context |
| `Route.totalVolumeM3` | domain (nullable string→float) | No | **Omit** — redundant with weight+parcels for common use |
| `Route.aiAnalysis` | domain (jsonb) | No | **Omit** — variable schema, unstable UI |
| `Route.autoReoptimize` | domain (bool) | No | **Omit** — internal state |
| `Route.startAt` / `endAt` | domain | No | **Omit at row level** — implicit from status |
| `RouteStop.sequence`, `.address`, `.recipientName`, `.deliveryWindowStart`, `.deliveryWindowEnd` | domain | No | **Include** — via new subquery (next pending stop per route) |
| `RouteStop.deliveredAt` | domain | No | **Include as aggregation** — histogram bins (hour of day, 0-23) |

## Omission Decisions (explicit)

| Element | Decision | Justification |
|---|---|---|
| `totalVolumeM3` | Omit | Peso+bultos cubre >90% de casos; añadir después si surge necesidad concreta |
| `aiAnalysis` | Omit | Schema JSON sin contrato estable; render inestable entre rutas |
| `autoReoptimize` | Omit | Bandera de comportamiento interno, no información operativa |
| `startAt`/`endAt` a nivel fila | Omit | Implícito por `status=ACTIVE`; la ventana horaria relevante es de la próxima parada |
| Computed ETA real-time | Omit | No hay ETA persistido en `RouteStop`; calcularlo requiere OSRM/ML y excede scope |

## Approaches Considered

### Approach A — Extend existing DTO + new subqueries (**chosen**)
Extend `RouteListApiController` with projection of existing `Route` fields
already loaded, plus two new bounded subqueries (next pending stop, today's
delivered-at timestamps). Frontend extends the existing `RouteListItem`
interface and the existing `ExpandableRouteCard` component.

- **Ventaja:** minimal surface change; reuses the `stopCounts` subquery pattern
  already present at `RouteListApiController.php:74-92`; no new abstractions;
  no migration; backward compatible (additive JSON fields).
- **Ventaja:** single endpoint round-trip for the dashboard (lazy-load only on expand).
- **Desventaja:** two extra DB round-trips per dashboard expand (bounded to 5 routes);
  acceptable given the N=5 ceiling.

### Approach B — Separate enrichment endpoint per route
Keep the list endpoint lean; add `GET /api/admin/routes/{publicId}/enriched`
fetched per-row on render. The list still shows only name/driver/vehicle/progress;
the extra metrics load asynchronously per row.

- **Ventaja:** clean separation of list-view vs. detail-view data.
- **Desventaja:** N+1 pattern for the dashboard expand — 5 extra HTTP round-trips;
  flicker while each card loads individually; worse UX.
- **Desventaja:** two endpoints to maintain for one UI feature.
- **Rejected:** the cost of N+1 outweighs any architectural purity benefit at N=5.

### Approach C — Push metrics into the dashboard aggregate (`AdminDashboardResponse.metrics`)
Include the top-5 active routes directly in the dashboard payload, no lazy-load.

- **Ventaja:** zero extra round-trips on expand (data is pre-loaded).
- **Desventaja:** dashboard payload inflates for users who never expand the card;
  reverses the lazy-load decision made in PR #253 (Enfoque B of
  `2026-04-14-expand-routes-card.md`).
- **Desventaja:** introduces coupling between the dashboard aggregate builder and
  the Route list projection — same data shape duplicated in two paths.
- **Rejected:** contradicts prior decision to keep dashboard payload lean.

## Trade-offs accepted

1. **`deliveryWindowStart/End` in place of computed ETA.** `RouteStop` has no
   persisted ETA field; computing one requires OSRM + current GPS position, which
   exceeds this scope. The committed delivery window is a usable proxy.
2. **Volume (`m3`) omitted.** Peso + bultos covers the common operational need;
   adding volume is cheap (one field) but clutters a 4-item metric line. Add only
   when a concrete use case appears.
3. **Histogram bucketed by server local hour.** Simpler than per-user TZ
   normalization; acceptable because admins viewing the dashboard are in the
   same ops timezone as the server. Revisit if multi-region admin access is added.
4. **Histogram is today-only.** Lifetime histogram requires defining a rolling
   window and complicates the "progress today" narrative. Today-only keeps the
   sparkline interpretable.
5. **No computed ETA.** See trade-off #1. This is the primary intentional gap.

## Design

### DTO shape (backend response — per item in `items[]`)

```jsonc
{
  // Existentes (sin cambios)
  "publicId": "01HX...",
  "name": "Ruta Norte 2026-04-21",
  "customerName": "ACME Corp",
  "vehicleName": "Vehiculo 1",
  "driverName": "Jose",
  "driverEmail": "jose@example.com",
  "status": "ACTIVE",
  "deliveredStops": 2,
  "totalStops": 8,

  // Nuevos — #1 optimización
  "totalDistanceKm": 87.4,
  "estimatedDurationMinutes": 260,

  // Nuevos — #3 carga
  "totalWeightKg": 1243.5,
  "totalParcels": 23,

  // Nuevo — #2 próxima parada (null si no hay pendientes)
  "nextStop": {
    "sequence": 3,
    "address": "Av. Libertador 1234, CABA",
    "recipientName": "Juan Pérez",
    "windowStart": "2026-04-21T11:00:00-03:00",
    "windowEnd":   "2026-04-21T13:00:00-03:00"
  },

  // Nuevo — #4 histograma (entregas por hora local 0-23, solo hoy)
  "deliveryHistogram": [0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 2, 4, 3, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]
}
```

**All new fields are nullable.** When data is missing (e.g., route not optimized,
no pending stops, no deliveries today), render is suppressed gracefully — no
placeholder strings like "N/A".

### Frontend `RouteListItem` TypeScript shape

Extend existing interface at `frontend/src/api/types.ts:329-339`:

```ts
export interface RouteNextStop {
  sequence: number;
  address: string;
  recipientName: string | null;
  windowStart: string | null;  // ISO 8601
  windowEnd: string | null;    // ISO 8601
}

export interface RouteListItem {
  publicId: string;
  name: string;
  customerName: string | null;
  vehicleName: string | null;
  driverName: string | null;
  driverEmail: string | null;
  status: 'PLANNED' | 'ACTIVE' | 'DONE' | 'CANCELLED';
  deliveredStops: number;
  totalStops: number;
  // New
  totalDistanceKm: number | null;
  estimatedDurationMinutes: number | null;
  totalWeightKg: number | null;
  totalParcels: number | null;
  nextStop: RouteNextStop | null;
  deliveryHistogram: number[] | null;
}
```

### Rendered card — per route row

```
Ruta Norte 2026-04-21                ━━━━━━─────  2/8
Jose · Vehiculo 1 · ACME Corp
87 km · 4h 20min · 1.2t · 23 bultos
Próxima: Av. Libertador 1234 (Juan Pérez)
Ventana: 11:00–13:00
▁▂▅█▅▂▁▁▁▁▁▁▁▁▁▁▁▁▁▁▁▁▁▁
```

**Layout rules:**
- Metric line (`km · min · t · bultos`): skip if all four are null.
- Next stop: skip entire block if `nextStop` is null.
- Window: skip line if both window bounds null; render as `HH:mm–HH:mm` in
  local time if both present; single bound shown as `≥ HH:mm` or `≤ HH:mm`.
- Sparkline: skip if `deliveryHistogram` is null OR all-zeros.

### Formatters (new, frontend utils)

```ts
formatKm(n: number): string            // 87.4 → "87 km", 3.5 → "3.5 km", 1234 → "1,234 km"
formatDuration(min: number): string    // 260 → "4h 20min", 45 → "45min", 90 → "1h 30min"
formatWeight(kg: number): string       // 1243.5 → "1.2t", 450 → "450 kg"
formatParcels(n: number): string       // 23 → "23 bultos", 1 → "1 bulto"
formatTimeWindow(start: string | null, end: string | null): string | null
```

These go in `frontend/src/widgets/DashboardKpisWidget.tsx` as local helpers (single
consumer for now — extract only if a second consumer appears).

## Backend changes

**File:** `backend/src/Controller/Api/Admin/RouteListApiController.php`

1. **Extend item projection** (lines 94-108) — add 4 scalar fields from `Route` getters.
2. **New subquery: next pending stop per route.** Follows the `stopCounts` pattern
   (lines 74-92) — single query with `IN (:routes)` and grouping by route id.
   Keep only one result per route (min sequence where `status = PENDING`).
3. **New subquery: today's delivered-at timestamps** per route — then aggregate
   into 24-hour bins in PHP (simpler than Postgres `EXTRACT`+`GROUP BY` for small N).

Two additional queries, both bounded by `limit = 5` (the dashboard call site).
Negligible DB cost.

## Frontend changes

**Files:**
- `frontend/src/api/types.ts` — extend `RouteListItem`, add `RouteNextStop`.
- `frontend/src/widgets/DashboardKpisWidget.tsx` — extend `ExpandableRouteCard`:
  - Import `SparklineSVG` (already used elsewhere in the file).
  - Add formatter helpers.
  - Replace the single-line driver/vehicle row with a 3-4 line block as per layout above.

No new dependencies. No new UI primitives. Reuses `SparklineSVG`.

## Testing

**New:** `backend/tests/Unit/Controller/Admin/RouteListApiControllerTest.php`
- Covers: DTO projection includes all new fields
- Covers: `nextStop` = null when no pending stops remain
- Covers: `deliveryHistogram` bins `deliveredAt` timestamps correctly (only today)
- Covers: null-propagation when `Route.totalDistanceKm` etc. are null

This is TDD: tests written first (red), then extend controller (green), then refactor.

**Existing tests affected:** None — the `RouteListApiController` has no prior test.

## Non-goals (explicit)

- Computing real-time ETA from GPS + OSRM (separate future work).
- Showing route polyline mini-map (separate future work, requires SVG path generator).
- "Km saved vs. non-optimized" delta (no persisted baseline).
- Multi-day aggregation (histogram is today-only).
- Exposing `aiAnalysis` content.

## Deployment

No migration. No env vars. No config. Zero-downtime: adding fields to a JSON
response is backward compatible (clients not consuming the new fields are
unaffected).
