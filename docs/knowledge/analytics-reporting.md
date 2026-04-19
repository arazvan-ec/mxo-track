# Analytics & Reporting

**Última actualización:** 2026-04-19
**Estado:** Vigente

**When to consult:** Working on KPIs, dashboards (admin/operator), SLA reports, CSV/PDF exports, A/B comparison of optimization strategies, post-route metric capture, or any widget that surfaces aggregated delivery/route numbers.

## Data Flow: From Events to Dashboards

The pipeline is **event-driven for the durable record** and **on-demand (live query) for dashboards**:

```
Route/Stop state changes
    │
    ▼
Domain events (RouteCompleted, …)
    │
    ▼
PostRouteAnalysisListener ──► MessageBus (PostRouteAnalysisMessage)
                                        │
                                        ▼
                              PostRouteAnalysisHandler
                                        │
                      ┌─────────────────┼──────────────────┐
                      ▼                                    ▼
             PostRouteAnalyzer                  RoutePerformanceMetricFactory
             (AI textual analysis)              (numeric KPIs, immutable)
                      │                                    │
                      ▼                                    ▼
              Route.aiAnalysis                    route_performance_metric
                                                  (one row per completed route)

Dashboards / reports:
    HTTP request ──► Controller ──► *Service.collect()/calculate()
                                     │
                                     ├─► live COUNT/SUM over route_plan, route_stop, …
                                     └─► aggregate over route_performance_metric (historical)
```

**Two families of metrics:**
- **Operational (live):** `OperatorKpiService`, `AdminMetricsService`, `ReportingService`, `SlaMetricsService` query current state — no precomputed cache. Recomputed on every request.
- **Historical (captured):** `RoutePerformanceMetric` rows — written once when a route completes, used by `OptimizationAnalyticsController` and `BillingService` for cross-route comparisons.

**Capture timing:** `RoutePerformanceMetric` is persisted asynchronously via Messenger when `RouteCompleted` fires. There is no scheduled backfill job — routes completed before the pipeline existed have no metric row.

## KPI Catalog

KPIs surfaced by the dashboards, with formulas extracted from the services.

| KPI | Formula / definition | Source service | Consumed by |
|---|---|---|---|
| `activeRoutes` | `COUNT(Route) WHERE status IN (ACTIVE, PLANNED)` | `OperatorKpiService` | Operator dashboard KPI widget |
| `deliveriesToday` | `COUNT(RouteStop) WHERE status=DELIVERED AND deliveredAt >= today` | `OperatorKpiService` | Operator KPI |
| `exceptionsToday` | `COUNT(RouteStop) WHERE status=EXCEPTION AND route.status IN (ACTIVE,PLANNED,DONE)` | `OperatorKpiService` | Operator KPI |
| `completionRate` | `delivered / total_stops * 100` on ACTIVE/PLANNED routes (non-origin) | `OperatorKpiService` | Operator KPI |
| `successRate7d` | `delivered / (delivered+exception)` for stops in routes started in last 7d | `OperatorKpiService` | Operator KPI |
| `vehiclesWithPosition` | `COUNT(VehicleLastPosition)` | `OperatorKpiService` | Operator KPI |
| `topDrivers` | Top 3 drivers (7d) ordered by DELIVERED count, with success_rate | `OperatorKpiService` (raw SQL, uses `FILTER (WHERE…)`) | Operator KPI |
| `import_runs_today` / `_7d` | `COUNT(csv_import_run) WHERE created_at >= X` | `AdminMetricsService` | Admin `infrastructure_metrics` |
| `positions_ingested_last_hour` / `_24h` | `COUNT(vehicle_positions) WHERE server_time >= X` | `AdminMetricsService` | Admin infrastructure |
| `active_routes` / `pending_stops` | `COUNT ... WHERE status=X` | `AdminMetricsService` | Admin dashboard KPI |
| `route_status_breakdown` / `stop_status_breakdown` | `GROUP BY status` | `AdminMetricsService` | Admin KPI |
| `deliveries_today` / `failed_today` | `COUNT(route_stop) WHERE status=X AND delivered_at >= today` | `AdminMetricsService` | Admin KPI |
| `success_rate` (delivery report) | `delivered / (delivered+exception) * 100` | `ReportingService::getDeliveryReport` | Admin deliveries report |
| `avg_deliveries_per_route` | `total_delivered / DISTINCT routes (status=DONE)` | `ReportingService::getDeliveryReport` | Admin deliveries |
| `completion_rate` (customer) | `delivered / (delivered+exception+pending) * 100` | `ReportingService::getCustomerReport` | Admin customers |
| `avg_stops_per_hour` (driver) | `stops_delivered / SUM((end_at - start_at)/3600)` | `ReportingService::getDriverPerformance` | Driver report |
| `otif_rate` | `delivered within delivery_window_end / total_delivered` | `SlaMetricsService` | SLA report |
| `on_time_rate` | `on_time_delivered / (delivered+exception)` | `SlaMetricsService` | SLA report |
| `first_attempt_rate` | `delivered with no prior EXCEPTION shipment_event / total_delivered` | `SlaMetricsService` | SLA report |
| `avg_delivery_time_minutes` | `AVG(EXTRACT(EPOCH FROM (delivered_at - route.start_at))/60)` | `SlaMetricsService` | SLA report |
| `exception_rate_by_type` | `GROUP BY exception_code` over EXCEPTION stops | `SlaMetricsService` | SLA report |

KPI **widgets** (rendering, layout, registry wiring) are documented in `ui-frontend.md` → Registry-Driven Dashboard. Admin layout widgets: `dashboard_kpis`, `system_health`, `infrastructure_metrics`, `mini_reports`, `reports_banner`.

## Services and Controllers

| Service | Responsibility | Storage strategy |
|---|---|---|
| `OperatorKpiService` | Operator portal KPIs (today + 7d). Mixes DQL and raw SQL (PostgreSQL `FILTER (WHERE)`) | Live query only |
| `AdminMetricsService` | Admin infrastructure counters (imports, positions, status breakdowns). Uses `Connection` directly | Live query, raw SQL |
| `ReportingService` | Customer / driver / delivery reports + trend charts + status distribution | Live DQL |
| `SlaMetricsService` | OTIF, on-time, first-attempt, SLA trend, driver ranking | Live raw SQL (PostgreSQL-specific) |
| `StrategyComparisonService` | A/B comparison of route optimizers on the same shipment set | Persists `OptimizationStrategyComparison` |
| `RoutePerformanceMetricFactory` | Builds a `RoutePerformanceMetric` from a completed `Route` + snapshot + optimization log | Writes once via handler |
| `PostRouteAnalysisHandler` | Async handler: runs AI analysis + calls the factory | Reads/writes Route + metric |

| Controller | Route | Audience | Purpose |
|---|---|---|---|
| `Admin/ReportController` | `/admin/reports/{deliveries,drivers,customers}` + `/export/*.csv` | `ROLE_ADMIN` | Twig reports + CSV stream |
| `Admin/SlaReportController` | `/admin/reports/sla`, `/data`, `/export` | `ROLE_ADMIN` | SLA HTML, JSON, Dompdf PDF |
| `Api/OptimizationAnalyticsController` | `/api/admin/optimization/{metrics,address-risks,reopt-history}` | `ROLE_OPERATOR` | Aggregated `RoutePerformanceMetric` + `AddressRisk` + re-opt history |
| `Api/AdminDashboardController` | `/api/admin/dashboard` | `ROLE_ADMIN` | Combined payload: health, live, metrics, daily_deliveries, top_drivers |
| `Api/DashboardReportsController` | `/api/admin/dashboard-reports` | `ROLE_ADMIN` | `daily_deliveries` + `top_drivers` subset |
| `Operator/OperatorDashboardController` | `/operator`, `/operator/dashboard/kpis` | `ROLE_ADMIN` (also covers operators) | Twig dashboard + JSON KPI endpoint |

There is **no `AdminMetricsController`** — `AdminMetricsService` is invoked through `AdminDashboardController` and `OperatorDashboardController`.

## Report Types

| Report | Audience | Source | Export |
|---|---|---|---|
| Deliveries summary | Admin | `ReportingService::getDeliveryReport` + `getTrendData` + `getStopStatusDistribution` | CSV (`/admin/reports/export/deliveries.csv`) |
| Driver performance / ranking | Admin | `ReportingService::getDriverRanking` / `getDriverPerformance` | CSV (`/admin/reports/export/drivers.csv`) |
| Customer breakdown | Admin | `ReportingService::getCustomerReport` (iterated over active customers) | HTML only |
| SLA report | Admin | `SlaMetricsService::calculateSla` | HTML + JSON (`/data`) + PDF (Dompdf, `/export`) |
| Optimization analytics | Admin/Operator (API) | `RoutePerformanceMetricRepository::getMetricsByOptimizer`, `AddressRiskRepository`, `RouteEvent` (REOPTIMIZED) | JSON only |
| Operator KPIs | Operator / Admin | `OperatorKpiService::collectKpis` | JSON |
| Zone / address risk | Admin/Operator | `AddressRisk` entity (`exception_rate`, `is_high_risk`, min 5 deliveries) | JSON via `/api/admin/optimization/address-risks` |

## A/B Testing: Strategy Comparison

`StrategyComparisonService::compare()` runs **all registered route optimizers** on the same shipment + vehicle set via `ProviderFactoryRegistry::getAvailableProviders()['route_optimizer']`. For each optimizer it calls `RouteBuilder::buildRoutes()` and records total distance, duration, route count.

When ≥2 optimizers produce results it persists an `OptimizationStrategyComparison`:

- `strategyA` / `strategyB` — JSON `{strategy, params, result: {distance_km, duration_min, stops, unassigned}}`
- `chosen` — `'a' | 'b' | 'neither'` (currently chooses the shorter distance)
- `chosenReason` — human-readable justification
- `actualOutcome` + `outcomeRecordedAt` — populated later via `recordOutcome()` once the chosen strategy's route runs to completion (closing the feedback loop)
- `shipmentCount` — sample size
- `customer` — optional multi-tenant scope (nullable, `ON DELETE SET NULL`)

Only **two** strategies are persisted per comparison (first two in the registry iteration order). Extra optimizer results appear in the return array but are not stored.

## Metric Capture Timing

| Data | When written | Trigger |
|---|---|---|
| `RoutePerformanceMetric` | Async, after `RouteCompleted` event | `PostRouteAnalysisListener` → `PostRouteAnalysisMessage` → handler |
| `Route.aiAnalysis` | Same handler, same transaction | `PostRouteAnalyzer::analyze()` |
| `OptimizationStrategyComparison` (pre-execution) | Synchronous inside `StrategyComparisonService::compare()` | Called from planning/test flows |
| `OptimizationStrategyComparison.actualOutcome` | Manually, via `recordOutcome()` — **no automatic listener yet** | TBD (feedback loop incomplete) |
| `AddressRisk` | Updated elsewhere (exception/delivery listeners) — not owned by this module | Per-delivery |

**Idempotency:** `PostRouteAnalysisHandler` guards with `findOneBy(['route' => $route])` before creating a metric — safe to replay the message. A `UniqueConstraint` on `route_id` also enforces this at the database level.

**No scheduled aggregation:** Dashboards recompute every request. There is no nightly cron producing a `daily_metrics` table and no Redis caching. The `LearningMetricsCommand` (CLI) exists for ad-hoc aggregation but is not scheduled.

**Derived KPIs** (computed inside `RoutePerformanceMetricFactory`):
- `deliverySuccessRate = deliveredCount / totalStops * 100`
- `kmSaved = snapshot.distanceBeforeKm - snapshot.distanceAfterKm` (null if snapshot missing)
- `timeSavedMinutes = plannedDurationMinutes - actualDurationMinutes`
- `planAccuracyPercent = (1 - |actual - planned| / planned) * 100`

If any input is null (no snapshot, route without `endAt`, etc.) the KPI is stored as null rather than zero — aggregate queries must use `AVG` which ignores nulls, not `SUM / COUNT`.

## Frontend Dashboards

| Page | Path | File | Data hook |
|---|---|---|---|
| Admin dashboard | `/app/admin/dashboard` | `frontend/src/pages/admin/AdminDashboardPage.tsx` | `useAdminDashboard` (GET `/api/admin/dashboard`) + `usePageLayout('admin_dashboard')` |
| Operator dashboard | `/app/admin/operator-dashboard` | `frontend/src/pages/admin/OperatorDashboardPage.tsx` | `useFleetMapData` + `useFleetKpi` + `usePageLayout('fleet_map')` |
| Twig operator dashboard | `/operator` | `backend/templates/operator/dashboard.html.twig` | Server-rendered `AdminMetricsService::collect()` |

Both React pages use `WidgetRenderer` + the widget registry. Admin page is pure chrome + widgets; operator page combines a live fleet map with a `BottomSheet` that renders registry widgets. The admin page greeting header (greeting, date, `last_ingestion.seconds_ago`) is page chrome — not a widget — because it depends on the request-scoped user and timestamp.

**Widget registry layouts** (see `ui-frontend.md` for widget details):
- `admin_dashboard` → `dashboard_kpis`, `system_health`, `infrastructure_metrics`, `mini_reports`, `reports_banner`
- `fleet_map` (reused by operator page) → live fleet KPIs + active routes panel

**Payload shape** (`GET /api/admin/dashboard`): `{ health, live, metrics, daily_deliveries, top_drivers, generated_at }`. The object is passed wholesale as `pageData` to `WidgetRenderer`; each widget selects the sub-tree it needs via its `dataSelector`.

## Key Files

- **Services:** `backend/src/Service/{OperatorKpiService,AdminMetricsService,ReportingService,SlaMetricsService,StrategyComparisonService,RoutePerformanceMetricFactory}.php`
- **Async pipeline:** `backend/src/EventListener/Domain/PostRouteAnalysisListener.php`, `backend/src/Message/PostRouteAnalysisMessage.php`, `backend/src/MessageHandler/PostRouteAnalysisHandler.php`
- **Entities:** `backend/src/Entity/{RoutePerformanceMetric,OptimizationStrategyComparison,AddressRisk}.php`
- **Repositories:** `backend/src/Repository/{RoutePerformanceMetricRepository,OptimizationStrategyComparisonRepository,AddressRiskRepository}.php`
- **Controllers:** `backend/src/Controller/Admin/{ReportController,SlaReportController}.php`, `backend/src/Controller/Api/{AdminDashboardController,DashboardReportsController,OptimizationAnalyticsController}.php`, `backend/src/Controller/Operator/OperatorDashboardController.php`
- **Frontend:** `frontend/src/pages/admin/{AdminDashboardPage,OperatorDashboardPage}.tsx`
- **Templates:** `backend/templates/admin/report/*.html.twig`, `backend/templates/admin/reports/sla{,_export}.html.twig`, `backend/templates/operator/dashboard.html.twig`

## Gotchas

- **No caching layer.** Every dashboard hit reruns raw SQL / DQL. On large tenants, `ReportingService::getTrendData(period=week, periods=12)` fires 36 queries (12 periods × 3 sub-queries) — watch for N+1 on growth. Consider materialized views before adding a cache.
- **Time zones.** All services call `new \DateTimeImmutable('today midnight')` or `'-7 days midnight'` with **no explicit timezone** — they use PHP's `date.timezone` (container default). `delivered_at::time <= delivery_window_end` in SLA SQL compares **raw times without tz conversion** — delivery windows assume the same tz as stored `delivered_at`. Cross-tenant multi-tz analysis is not supported.
- **No backfill.** `RoutePerformanceMetric` only exists for routes that completed **after** the listener was wired. Aggregate queries filtered by `createdAt >= since` silently skip older routes. If you need pre-existing history, you must backfill via a dedicated command — none exists today.
- **Customer filter applied through routes, not shipments.** `ReportingService::getDeliveryReport` filters by `r.customer = :customer`. Multi-tenant `CustomerTenantFilter` also applies to `Route`, so admin-only callers may bypass it; double-check role context when building admin reports.
- **Soft-deleted routes.** `OperatorKpiService::getTopDrivers` and all `SlaMetricsService` SQL explicitly add `r.deleted_at IS NULL`. Other methods rely on the Doctrine SQL filter — if you disable the filter for an admin query, you may count deleted rows.
- **`avg_savings_percent` is mislabelled.** `RoutePerformanceMetricRepository::getCustomerAggregateMetrics` aliases `AVG(planAccuracyPercent)` as `avg_savings_percent`. Plan accuracy ≠ savings percentage — rename before new consumers depend on it.
- **StrategyComparison only stores 2 strategies.** If the registry has 3+ optimizers, comparisons lose the extras. The `chosen` field uses `a`/`b` — extending requires schema change.
- **Admin vs operator role.** `OperatorDashboardController` is gated by `ROLE_ADMIN` despite its name. `OptimizationAnalyticsController` accepts `ROLE_OPERATOR`. Check the attribute, not the controller name, when adding new endpoints.
- **Mixed DQL/SQL.** `OperatorKpiService::getTopDrivers` and all `SlaMetricsService` internals use raw SQL for PostgreSQL features (`FILTER (WHERE …)`, `EXTRACT(EPOCH …)`, `::time`). These will break on non-PG databases — the codebase is PostgreSQL-only (see `deployment.md`).
