# Plan: Optimización Integral del Routing — Phase 3 (UX + Políticas)

**Fecha:** 2026-04-10
**Spec:** `docs/superpowers/specs/2026-04-10-optimize-routing-design.md`
**Branch:** `claude/optimize-routing-NE2kv`
**Scope:** Phase 3 completa (3-A, 3-B, 3-C)
**TDD:** Opción A — tests escritos por modelo principal, verificar RED, implementar en paralelo

---

## Phase 3: UX + Políticas

### [parallel] Wave 1: Backend — StrategyComparisonService + ReoptimizationPolicy entity (3-A + 3-B backend)

#### Tarea 1a: `StrategyComparisonService`

**Archivo nuevo:** `backend/src/Service/StrategyComparisonService.php`
**Cambio:** Ejecuta N optimizadores en paralelo sobre mismo shipment set, retorna tabla comparativa.
- Constructor: `ProviderFactoryRegistry`, `RouteBuilder`, `OptimizationLogger`
- Method: `compare(array $shipments, array $vehicles, Customer $customer, ?CustomerLocation $origin, int $maxStops): array`
  - Para cada optimizer en registry (filtrado por RouteOptimizer type): llama `RouteBuilder.buildRoutes()` con `$optimizerOverride`
  - Retorna: `[{optimizer_name, distance_km, duration_min, stop_count, unassigned_count}]`
  - Persiste `OptimizationStrategyComparison` entity con resultados A vs B

**TDD (RED primero, luego GREEN):**
1. Test: 2 optimizadores disponibles → retorna array con 2 resultados, cada uno con métricas
2. Test: 1 optimizador → retorna 1 resultado
3. Test: OptimizationStrategyComparison persistida con chosen='a'

**Test file:** `backend/tests/Unit/Service/StrategyComparisonServiceTest.php`

#### Tarea 1b: `ReoptimizationPolicy` entity

**Archivo nuevo:** `backend/src/Entity/ReoptimizationPolicy.php`
**Cambio:** Entity per customer con:
- `customer` (ManyToOne Customer, unique)
- `triggers` (JSON array: ['on_exception', 'on_skip', 'on_delay'])
- `delayThresholdMinutes` (int, default 30)
- `consecutiveExceptionThreshold` (int, default 2)
- `cooldownMinutes` (int, default 10)
- `enabled` (bool, default true)
- Standard PublicIdTrait, timestamps

**TDD:**
1. Test: crear policy con triggers → verificar accesors
2. Test: `allowsTrigger('on_exception')` → true si está en triggers array
3. Test: `allowsTrigger('on_delay')` → false si no está

**Test file:** `backend/tests/Unit/Entity/ReoptimizationPolicyTest.php`
**Migration:** `backend/migrations/VersionXXX_AddReoptimizationPolicy.php`

---

### Wave 2: Backend — Migrate subscribers + comparison API (3-A + 3-B wiring)

#### Tarea 2a: Migrate 3 reopt subscribers de boolean a policy

**Archivos:**
- `backend/src/EventSubscriber/ExceptionReoptimizationSubscriber.php`
- `backend/src/EventSubscriber/SkipReoptimizationSubscriber.php`
- `backend/src/EventSubscriber/DelayReoptimizationSubscriber.php`

**Cambio:** Reemplazar `$route->isAutoReoptimize()` por resolución de policy:
1. Cada subscriber recibe `EntityManagerInterface` (para query ReoptimizationPolicy por customer)
2. Check: `$policy->isEnabled() && $policy->allowsTrigger('on_exception')` (o 'on_skip', 'on_delay')
3. `DelayReoptimizationSubscriber` lee `delayThresholdMinutes` y `cooldownMinutes` de la policy en vez de DI params
4. Fallback: si no hay policy → usa `Route.autoReoptimize` como antes (backward compatible)

**TDD:**
1. Test: subscriber con policy que permite 'on_exception' → re-optimiza
2. Test: subscriber con policy que NO permite 'on_exception' → skip
3. Test: subscriber sin policy → fallback a Route.autoReoptimize boolean
4. Test: DelaySubscriber lee threshold de policy (45 min en vez de default 30)

**Test file:** `backend/tests/Unit/EventSubscriber/ReoptimizationPolicySubscriberTest.php`

#### Tarea 2b: Strategy comparison endpoint

**Archivo:** `backend/src/Controller/Admin/RoutePlannerController.php`
**Cambio:** Nuevo endpoint `POST /admin/route-planner/compare` que:
1. Recibe mismo payload que `/preview` (shipments, vehicles, origin, maxStops)
2. Llama `StrategyComparisonService.compare()`
3. Retorna tabla comparativa JSON

**TDD:**
1. Test: POST con shipments/vehicles → retorna array de comparaciones
2. Test: endpoint accesible solo con ROLE_OPERATOR

**Test file:** `backend/tests/Unit/Controller/Admin/RoutePlannerCompareTest.php`

#### Tarea 2c: ReoptimizationPolicy CRUD API

**Archivo nuevo:** `backend/src/Controller/Api/ReoptimizationPolicyApiController.php`
**Cambio:** CRUD endpoints:
- `GET /api/admin/reoptimization-policies` — list per customer
- `GET /api/admin/reoptimization-policies/{publicId}` — get one
- `POST /api/admin/reoptimization-policies` — create
- `PUT /api/admin/reoptimization-policies/{publicId}` — update
- `DELETE /api/admin/reoptimization-policies/{publicId}` — delete

**TDD:**
1. Test: create policy → 201 con public_id
2. Test: update triggers → 200 con nuevo array
3. Test: list → array de policies

**Test file:** `backend/tests/Unit/Controller/Api/ReoptimizationPolicyApiControllerTest.php`

---

### Wave 3: Frontend — Route Planner comparison + policy config (3-A + 3-B frontend)

#### Tarea 3a: Route Planner Step 2 — comparison UI

**Archivo:** `frontend/src/pages/admin/RoutePlannerPage.tsx`
**Cambio en Step 2:**
1. Botón "Comparar optimizadores" que llama `/admin/route-planner/compare`
2. Tabla side-by-side con métricas por optimizador
3. User selecciona optimizador preferido → se usa en preview

**Archivo:** `frontend/src/api/hooks/useRoutePlanner.ts`
**Cambio:** Nuevo hook `useCompareOptimizers()` con `useMutation`

#### Tarea 3b: Admin Customer — policy configuration

**Archivos nuevos:**
- `frontend/src/pages/admin/ReoptimizationPolicyPage.tsx` — config UI per customer
- `frontend/src/api/hooks/useReoptimizationPolicy.ts` — CRUD hooks

**UI:** Formulario con:
- Toggle enabled/disabled
- Checkboxes: on_exception, on_skip, on_delay
- Inputs: delayThresholdMinutes, consecutiveExceptionThreshold, cooldownMinutes

---

### Wave 4: Frontend — Optimization Analytics Dashboard (3-C)

#### Tarea 4a: Backend analytics endpoints

**Archivo nuevo:** `backend/src/Controller/Api/OptimizationAnalyticsController.php`
**Endpoints:**
- `GET /api/admin/optimization/metrics` — metrics por optimizador (reusa RoutePerformanceMetricRepository)
- `GET /api/admin/optimization/address-risks` — top risky addresses (AddressRiskRepository)
- `GET /api/admin/optimization/reopt-history` — historial de re-optimizaciones (RouteEventRepository)

**TDD:**
1. Test: /metrics retorna stats agrupadas
2. Test: /address-risks retorna top 20 high-risk addresses
3. Test: /reopt-history retorna eventos con trigger type

**Test file:** `backend/tests/Unit/Controller/Api/OptimizationAnalyticsControllerTest.php`

#### Tarea 4b: React Optimization Dashboard page

**Archivo nuevo:** `frontend/src/pages/admin/OptimizationDashboardPage.tsx`
**Componentes:**
- Optimizer performance chart (bar chart: distance, duration por optimizer)
- Address risk heatmap (mapa con markers de risk)
- Re-optimization history timeline
- Plan accuracy trend

**Archivo:** `frontend/src/api/hooks/useOptimizationAnalytics.ts` — hooks para los endpoints

---

### Wave 5: Migration + Verification

#### Tarea 5a: Doctrine migration para ReoptimizationPolicy

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate -n
```

#### Tarea 5b: Full test suite + lint

```bash
cd backend && php vendor/bin/phpunit
make lint
cd frontend && npm run build
```

#### Tarea 5c: Commit + push

---

## Dependencias entre waves

```
Wave 1 (entity + comparison service) — parallel
    ↓
Wave 2 (migrate subscribers + APIs) — needs entity + service
    ↓
Wave 3 (frontend planner + policy UI) — needs APIs
Wave 4 (analytics dashboard) — needs APIs, parallel con Wave 3
    ↓
Wave 5 (migration + verification)
```

## Resumen de archivos

| Archivo | Wave | Tipo |
|---------|------|------|
| `src/Service/StrategyComparisonService.php` | 1 | **Nuevo** |
| `src/Entity/ReoptimizationPolicy.php` | 1 | **Nuevo** |
| 3 EventSubscriber files | 2 | Refactor (boolean → policy) |
| `src/Controller/Admin/RoutePlannerController.php` | 2 | Extend (+compare) |
| `src/Controller/Api/ReoptimizationPolicyApiController.php` | 2 | **Nuevo** |
| `src/Controller/Api/OptimizationAnalyticsController.php` | 4 | **Nuevo** |
| `frontend/src/pages/admin/RoutePlannerPage.tsx` | 3 | Extend (comparison UI) |
| `frontend/src/pages/admin/ReoptimizationPolicyPage.tsx` | 3 | **Nuevo** |
| `frontend/src/pages/admin/OptimizationDashboardPage.tsx` | 4 | **Nuevo** |
| Frontend hooks (3 nuevos) | 3-4 | **Nuevo** |
| Migration | 5 | **Nuevo** |
| Tests (~10 nuevos) | 1-4 | **Nuevo** |
