# Spec: Navigation API Endpoint (v2)

**Date:** 2026-03-23
**Bounded Context:** Pragmatic (UI/Navigation)
**Approach:** A — API endpoint + React consume
**Prior attempt:** v1 spec exists but implementation was reverted due to workflow violations. This v2 includes complete route registry inventory.

---

## Problema

`NavigationSidebar.tsx` tiene items hardcodeados por rol en 3 funciones. No hay SSoT server-side. Además, hay rutas registradas en Symfony que no aparecen en ningún menú (10+ rutas faltantes para admin).

## Alternativas evaluadas

### Approach A: API endpoint + React consume (elegido)
- `GET /api/navigation` retorna sections filtradas por rol
- Labels traducidos server-side via `TranslatorInterface`
- React consume con `useNavigation()` hook
- **Pro:** SSoT real, roles en PHP, i18n server-side, funciona en Twig widget y SPA
- **Con:** 1 request extra (~1KB, cacheable 1h)

### Approach B: YAML config + Twig Extension
- **Pro:** Declarativo
- **Con:** Requiere sincronizar YAML con router manualmente; no filtra por rol automáticamente

### Approach C: Router-based auto-discovery
- **Pro:** Nunca se desincroniza
- **Con:** Over-engineering; necesita metadatos (icon, section, order) en rutas

## Decisión

**Approach A** — endpoint dedicado, separación de concerns, probado en v1.

---

## Route Registry Inventory

Complete inventory from `php bin/console debug:router`. Only GET/ANY index-level routes (not sub-pages like /edit, /new, /show, nor data endpoints like /data, /kpis, /export).

### Admin routes

| Route | Path | In menu (current) | Decision |
|-------|------|-------------------|----------|
| admin_dashboard | /admin | Yes | Keep |
| operator_dashboard | /app/admin/operator-dashboard | Yes | Keep |
| admin_vehicles_index | /admin/vehicles | Yes | Keep |
| admin_drivers_index | /admin/drivers | Yes | Keep |
| admin_routes_index | /admin/routes | Yes | Keep |
| admin_shipments_index | /admin/shipments | Yes | Keep |
| admin_shipments_import | /admin/shipments/import | **No** | **Add** — business feature, frequent access |
| admin_route_planner_index | /app/admin/route-planner | Yes | Keep |
| admin_route_templates_manage | /admin/route-templates | Yes | Keep |
| admin_customers_index | /admin/customers | Yes | Keep |
| admin_integrations_index | /admin/integrations | Yes | Keep |
| admin_users_index | /admin/users | Yes | Keep |
| admin_reports_index | /admin/reports | Yes | Keep |
| admin_reports_sla | /admin/reports/sla | Yes | Keep |
| admin_reports_exception_map | /app/admin/exception-map | Yes | Keep |
| admin_zone_trends | /admin/reports/zone-trends | **No** | **Add** — business report, same level as SLA |
| admin_billing_index | /admin/billing | Yes | Keep |
| admin_optimization_logs_index | /admin/optimization-logs | **No** | **Add** — optimization diagnostics |
| admin_ai_assistant | /admin/ai-assistant | **No** | **Add** — available for operators |
| fleet_map | /app/admin/fleet-map | Yes | Keep |
| admin_test_routing_* | /admin/test-routing | **No** | **Add** — routing diagnostics |
| admin_debug_routing | /admin/debug/routing | **No** | **Add** — routing diagnostics |
| admin_fixtures_index | /admin/fixtures | **No** | **Add** (Dev section) |
| admin_commit_story | /admin/commit-story | **No** | **Add** (Dev section) |
| admin_health | /admin/health | **No** | **Omit** — technical endpoint, not a navigable page |
| notification_index | /notifications | **No** | **Add** — all authenticated roles |
| app_search | /search | **No** | **Add** — all authenticated roles |

### Customer routes

| Route | Path | In menu (current) | Decision |
|-------|------|-------------------|----------|
| customer_dashboard | /customer/dashboard | Yes (wrong URL: /customer) | **Fix** URL to /customer/dashboard |
| customer_routes_index | /customer/routes | Yes | Keep |
| customer_shipments_index | /customer/shipments | Yes | Keep |
| customer_reports_index | /customer/reports | Yes | Keep |
| fleet_map | /app/admin/fleet-map | Yes | Keep |
| notification_index | /notifications | **No** | **Add** |
| app_search | /search | **No** | **Add** |

### Driver routes

| Route | Path | In menu (current) | Decision |
|-------|------|-------------------|----------|
| driver_routes_index | /driver/routes | Yes | Keep |
| notification_index | /notifications | **No** | **Add** |

### Omitted routes (with justification)

| Route pattern | Justification |
|---------------|---------------|
| `*_edit`, `*_new`, `*_show`, `*_delete` | Sub-pages navigated from index lists |
| `*_data`, `*_kpis`, `*_export` | AJAX/data endpoints, not navigable pages |
| `admin_health`, `admin_health_live` | Technical monitoring, no UI page |
| `admin_*_branches` (commit-story sub) | Sub-page of commit-story |
| `admin_route_planner_*` (sub-endpoints) | Sub-API of planner, not navigable |
| `customer_dashboard_kpis`, `customer_dashboard_optimization_kpis` | AJAX data for dashboard |
| `operator_dashboard_kpis`, `operator_dashboard_live` | AJAX data for operator dashboard |

---

## Existing Functionality Inventory

| # | Element | Location | Description |
|---|---------|----------|-------------|
| 1 | `_sidebar_content.html.twig` | `backend/templates/` | Deprecated Twig sidebar, not included in base.html.twig since 2026-03-22 |
| 2 | `NavigationSidebar.tsx` | `frontend/src/components/layout/` | React component with 3 hardcoded nav functions. 16 SVG icons. Overlay/inline modes. |
| 3 | `sidebar-widget.tsx` | `frontend/src/` | Mounts NavigationSidebar in overlay mode for Twig pages |
| 4 | `topbar-widget.tsx` | `frontend/src/` | Mounts TopBar for Twig pages |
| 5 | `MeController` (`/api/me`) | `backend/src/Controller/Api/` | Returns user identity info |
| 6 | `useMe()` hook | `frontend/src/api/hooks/` | React hook consuming /api/me |
| 7 | `base.html.twig` | `backend/templates/` | Mount points for React widgets |
| 8 | `DualMenuShell.tsx` | `frontend/src/components/layout/` | SPA shell using NavigationSidebar inline |
| 9 | Icons SVG dict | inline in `NavigationSidebar.tsx` | 16 icon keys mapped to JSX SVG |
| 10 | Translation keys | `backend/translations/messages.*.yaml` | `nav.*` and `sidebar.*` keys |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| 1. `_sidebar_content.html.twig` | **Delete** | Not included since 2026-03-22, fully replaced by React widget |
| 2. `NavigationSidebar.tsx` | **Transform** | Remove hardcoded functions, consume `useNavigation()`. Keep icons dict, modes, footer. |
| 3. `sidebar-widget.tsx` | **Keep as-is** | Bridge Twig-React, unchanged |
| 4. `topbar-widget.tsx` | **Keep as-is** | Not affected |
| 5. `MeController` | **Keep as-is** | Separate identity endpoint |
| 6. `useMe()` | **Keep as-is** | Still used for user footer |
| 7. `base.html.twig` | **Keep as-is** | Mount points unchanged |
| 8. `DualMenuShell.tsx` | **Keep as-is** | Passes NavigationSidebar as child |
| 9. Icons SVG dict | **Extend** | Add 5 new icons: notifications, search, import, optimization, ai |
| 10. Translation keys | **Extend** | Add ~12 new keys for new menu items |

---

## Detailed Design

### 1. API Endpoint: `GET /api/navigation`

**Controller:** `src/Controller/Api/NavigationController.php`
- `#[IsGranted('ROLE_USER')]`
- Inject `TranslatorInterface`
- Build sections by role, labels pre-translated
- `Cache-Control: private, max-age=3600`

**Admin sections:**
- Principal: dashboard, dashboard-live, notifications, search
- Operaciones: vehicles, drivers, routes, shipments, import-csv, planner, route-templates, customers, integrations
- Administracion: users, reports, sla, zone-trends, exception-map, billing, optimization-logs, ai-assistant
- Seguimiento: fleet-map, test-routing, debug-routing
- Dev Tools: fixtures, commit-story

**Customer sections:**
- Principal: dashboard (/customer/dashboard), notifications, search
- Mis Entregas: routes, shipments, reports
- Seguimiento: fleet-map

**Driver sections:**
- Conductor: routes, notifications

### 2. React: `useNavigation()` hook

Same pattern as `useMe()`: `useQuery` + `api.get<NavigationResponse>('/api/navigation')` + 1h staleTime.

### 3. NavigationSidebar.tsx changes

- Remove `getAdminNav()`, `getCustomerNav()`, `getDriverNav()`, `getNavSections()`
- Consume `useNavigation()` → `data?.sections ?? []`
- Add `resolveIcon(key)` function mapping string to SVG
- Add active state: `location.pathname === item.href`
- Add 5 new icon entries: notifications, search, import, optimization, ai

### 4. Cleanup

- Delete `backend/templates/_sidebar_content.html.twig`

---

## Risks

- **Low:** If `/api/navigation` fails, sidebar shows empty. Mitigated by short staleTime + error state.
- **Low:** Icon key mismatch. Mitigated by fallback to dashboard icon.
