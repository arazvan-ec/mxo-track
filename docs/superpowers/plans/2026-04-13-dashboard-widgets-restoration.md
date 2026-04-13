# Plan — 2026-04-13 — Dashboard Widgets Restoration (Phase 1)

**Spec:** `docs/superpowers/specs/2026-04-13-dashboard-widgets-restoration-design.md`
**Branch:** `claude/enhance-dashboard-widgets-R46AJ`
**Phase:** 1 of 2 (Phase 2 en PR posterior)

## Objetivo

Restaurar widget system en `AdminDashboardPage` con doble toggle (minimize +
detail) y enriquecer 4 widgets con breakdowns y gráficas reutilizando
componentes existentes. Mantener compatibilidad con el contrato actual del
endpoint `/api/admin/dashboard`.

## Estrategia: Phase 1 (v0) → ya es la versión madura

No hay split v0 / Mature en este PR porque la arquitectura objetivo
(WidgetRenderer + CollapsibleWidget extendido) ya está definida y probada.
Implementamos directamente el diseño final de Phase 1.

## Decomposición en Waves

### Wave 1 — Fundaciones [parallel: 3 tareas]

Las 3 tareas de Wave 1 son **independientes** (archivos disjuntos) y se ejecutan en
agentes paralelos.

#### **1a — Backend: extender `AdminMetricsService`** (TDD)
- **Archivos:**
  - `backend/src/Service/AdminMetricsService.php` (modificar)
  - `backend/src/Service/AdminMetricsService.php` — nuevos métodos privados:
    `routesData()`, `stopsData()`, `importsData()`, `positionsData()`,
    `deliveriesData()`, `infrastructureData()`
  - `backend/tests/Service/AdminMetricsServiceTest.php` (nuevo)
- **TDD:**
  1. Test `it_returns_routes_breakdown_with_status_counts` → fail
  2. Implementar `routesData()` con query `GROUP BY status` → green
  3. Test `it_returns_stops_today_breakdown` → fail
  4. Implementar `stopsData()` → green
  5. Repetir para imports, positions, deliveries, infrastructure
  6. Test `it_preserves_legacy_flat_keys_for_backward_compat` (active_routes,
     pending_stops, import_runs_today, positions_ingested_last_hour) → fail
  7. Implementar mapping legacy keys → green
- **Produce:** estructura JSON completa de `/api/admin/dashboard` con todos los
  datos del spec (sin uptime histórico — Phase 2)

#### **1b — Frontend: extender `CollapsibleWidget` con doble toggle** (TDD)
- **Archivos:**
  - `frontend/src/components/widgets/CollapsibleWidget.tsx` (modificar)
  - `frontend/src/components/widgets/CollapsibleWidget.test.tsx` (nuevo o ampliar)
- **TDD:**
  1. Test `renders detail toggle button when supportsDetail=true` → fail
  2. Añadir prop `supportsDetail` + botón ⤢ → green
  3. Test `toggling detail persists to localStorage` → fail
  4. Implementar storage key `mxo-dashboard-widget-{key}-detailed` → green
  5. Test `children render-prop receives detailed boolean` → fail
  6. Soportar `children: ReactNode | ((detailed: boolean) => ReactNode)` → green
  7. Test `does not render detail button when supportsDetail is false` → green
- **Produce:** API completa del componente para que widgets puedan recibir
  `detailed` y renderizar dos modos

#### **1c — Frontend: layout config + WidgetRenderer page-mode bridge**
- **Archivos:**
  - `frontend/src/components/bottom-sheet/WidgetRenderer.tsx` (modificar)
  - `frontend/src/widgets/registry.ts` (modificar — añadir `supportsDetail` a
    entries: dashboard_kpis, system_health, mini_reports,
    infrastructure_metrics, activity_feed)
  - `frontend/src/widgets/types.ts` (modificar — añadir `supportsDetail` a
    `WidgetRegistryMeta`)
  - `frontend/src/pages/admin/adminDashboardLayout.ts` (nuevo) — exporta
    `ADMIN_DASHBOARD_LAYOUT: LayoutConfig`
- **Cambios concretos:**
  - `WidgetRegistryMeta` añade `supportsDetail?: boolean` y
    `defaultDetailed?: boolean`
  - `WidgetRenderer` cuando `mode='page'` y `entry.supportsDetail`: pasa
    función render-prop a `CollapsibleWidget` que inyecta
    `expanded={detailed}` al widget interno
  - Layout exporta widgets en orden: dashboard_kpis, system_health,
    mini_reports, infrastructure_metrics, activity_feed (minimized default),
    reports_banner
- **Produce:** infraestructura para que Wave 2 pueda implementar widgets
  asumiendo doble toggle disponible

---

### Wave 2 — Widgets refactor [parallel: 4 tareas]

Las 4 tareas tocan **archivos disjuntos** (un widget cada una) y se ejecutan en
paralelo. Todas dependen de Wave 1 (1a backend data + 1b CollapsibleWidget API + 1c
registry).

#### **2a — DashboardKpisWidget detailed**
- **Archivos:**
  - `frontend/src/widgets/DashboardKpisWidget.tsx` (reescribir)
  - `frontend/src/widgets/DashboardKpisWidget.test.tsx` (nuevo)
- **TDD:**
  1. Test `compact mode renders 4 KPI cards with single number` → fail
  2. Implementar compact (similar a actual) → green
  3. Test `detailed mode for routes card shows status breakdown pills` → fail
  4. Implementar detailed routes con mini-pills → green
  5. Test `detailed mode renders MetricPairs for routes` → fail
  6. Implementar MetricPairs antes/después → green
  7. Repetir para stops, imports, positions
- **Componentes reutilizados:** `AnimatedCounter`, `SparklineSVG`,
  `MetricPairs`, `RadialGauge`, custom mini-pills (inline o componente nuevo
  `<KpiBreakdownPills>`)

#### **2b — SystemHealthWidget detailed**
- **Archivos:**
  - `frontend/src/widgets/SystemHealthWidget.tsx` (modificar)
  - `frontend/src/widgets/SystemHealthWidget.test.tsx` (nuevo o ampliar)
- **TDD:**
  1. Test `compact mode renders 6 RadialGauges` → fail (probable ya verde)
  2. Test `detailed mode shows latency stats per service` → fail
  3. Implementar fila por servicio con: gauge grande + label + latencia
     última + thresholds info → green
  4. Test `detailed mode marks degraded services with warning badge` → fail
  5. Implementar badge degraded → green
  6. Phase 1 nota: sparklines vacíos (placeholder) hasta Phase 2

#### **2c — MiniReportsWidget detailed**
- **Archivos:**
  - `frontend/src/widgets/MiniReportsWidget.tsx` (modificar)
  - `frontend/src/widgets/MiniReportsWidget.test.tsx` (nuevo o ampliar)
- **TDD:**
  1. Test `compact mode renders 7-day bars + top 5 drivers` → fail (probable ya verde)
  2. Test `detailed mode shows month total + delta vs previous month` → fail
  3. Implementar header con counter + delta % → green
  4. Test `detailed mode renders 7-day breakdown table` → fail
  5. Implementar tabla → green
  6. Test `detailed mode shows top 10 drivers (vs 5 compact)` → fail
  7. Implementar lógica de límite por modo → green
  8. Test `detailed mode renders top 5 customers` → fail
  9. Implementar lista clientes → green
  10. Tests para razones de fallo, sparkline 30d, hourly distribution
- **Componentes reutilizados:** `AnimatedBarChart`, `TopDriversList`,
  `RadialGauge`, `SparklineSVG`, `AnimatedCounter`

#### **2d — InfrastructureMetricsWidget detailed**
- **Archivos:**
  - `frontend/src/widgets/InfrastructureMetricsWidget.tsx` (modificar)
  - `frontend/src/widgets/InfrastructureMetricsWidget.test.tsx` (nuevo o ampliar)
- **TDD:**
  1. Test `compact mode renders 3 cards` → fail (probable ya verde)
  2. Test `detailed mode shows DB growth sparkline` → fail
  3. Implementar sparkline 30d → green (Phase 1: si el dato no existe en
     backend, mostrar placeholder y log warning)
  4. Test `detailed mode shows ingestion rate sparkline` → fail
  5. Implementar sparkline ingestión 24h → green
  6. Test `detailed mode shows oldest position record age` → fail
  7. Implementar counter retención → green
  8. Phase 1 nota: queue depth, cache hit, disk, table sizes son Phase 2
     — placeholder con tooltip "disponible próximamente"

---

### Wave 3 — Refactor AdminDashboardPage [secuencial, 1 tarea]

Depende de Wave 1c (layout) + Wave 2 (todos los widgets actualizados).

#### **3 — AdminDashboardPage migration**
- **Archivos:**
  - `frontend/src/pages/admin/AdminDashboardPage.tsx` (reescribir de 360 a ~80 líneas)
  - `frontend/src/pages/admin/AdminDashboardPage.test.tsx` (nuevo)
- **TDD:**
  1. Test `renders greeting header with date` → fail
  2. Implementar header inline (mantener `getGreeting`, `formatDate`,
     `formatSecondsAgo`) → green
  3. Test `renders WidgetRenderer with admin layout` → fail
  4. Reemplazar bento grid por `<WidgetRenderer mode='page' layout={ADMIN_DASHBOARD_LAYOUT} pageData={data} />` → green
  5. Test `shows loading and error states` → fail
  6. Mantener loading/error spinners → green
  7. Eliminar SERVICE_CONFIG, RadialGauge import, todas las KPI cards inline,
     bar chart inline, top drivers inline, infra cards inline, banner inline
- **Verificar:** página renderiza igual visualmente (tras minimización
  default), localStorage persistence funciona, 6 widgets visibles tras click
  en "expandir todos" mental

---

### Wave 4 — Verificación + capture [secuencial]

#### **4 — Verificación**
- `cd backend && make lint` (espera: clean)
- `cd backend && php vendor/bin/phpunit` (espera: 0 nuevos fallos vs baseline 11
  pre-existentes en driver_routes)
- `cd frontend && npm run build` (deploy command exacto — no `tsc --noEmit`)
- Smoke test manual: cargar dashboard, click minimize, click detail,
  recargar página → estado persiste

#### **5 — Capture + retrospective**
- Crear `docs/superpowers/execution-logs/2026-04-13-dashboard-widgets-restoration.md`
- Actualizar `docs/decisions/log.md` con la decisión del spec
- Actualizar `docs/codebase-manifest.md` con `make manifest`
- Commit final + push

## Dependencias (DAG)

```
Wave 1: 1a (backend) ⫼ 1b (CollapsibleWidget) ⫼ 1c (registry+layout)
            │                │                       │
            └────────┬───────┴───────┬───────────────┘
                     │               │
              produces dashboard data + widget API + registry config
                     │
Wave 2: 2a (KPIs) ⫼ 2b (SystemHealth) ⫼ 2c (MiniReports) ⫼ 2d (Infra)
                     │
                  produces all widgets compact+detailed modes
                     │
Wave 3: 3 (AdminDashboardPage refactor) — needs all widgets working
                     │
Wave 4: 4 verificación → 5 capture
```

## Validación de paralelización (file conflict check)

Verificado: ninguna pareja de tareas paralelas modifica el mismo archivo.

| Wave | Tarea | Archivos modificados |
|---|---|---|
| 1a | Backend service | `AdminMetricsService.php`, `AdminMetricsServiceTest.php` |
| 1b | CollapsibleWidget | `CollapsibleWidget.tsx`, `CollapsibleWidget.test.tsx` |
| 1c | Registry + layout | `WidgetRenderer.tsx`, `registry.ts`, `types.ts`, `adminDashboardLayout.ts` (nuevo) |
| 2a | KPIs | `DashboardKpisWidget.tsx`, `DashboardKpisWidget.test.tsx` |
| 2b | SystemHealth | `SystemHealthWidget.tsx`, `SystemHealthWidget.test.tsx` |
| 2c | MiniReports | `MiniReportsWidget.tsx`, `MiniReportsWidget.test.tsx` |
| 2d | Infra | `InfrastructureMetricsWidget.tsx`, `InfrastructureMetricsWidget.test.tsx` |
| 3 | AdminDashboardPage | `AdminDashboardPage.tsx`, `AdminDashboardPage.test.tsx` |

✅ Sin conflictos de archivos entre paralelas. Wave 2 todas dependen de Wave 1c
(`registry.ts`, `types.ts`) — leen pero no escriben.

## Total de tareas Phase 1

- Wave 1: 3 tareas paralelas
- Wave 2: 4 tareas paralelas
- Wave 3: 1 tarea
- Wave 4: 2 tareas (verificación + capture)
- **Total: 10 tareas**

## Riesgos y Mitigaciones

| Riesgo | Mitigación |
|---|---|
| Widget existente (`MiniReportsWidget`) tiene API distinta de la esperada | Wave 2c lee primero el archivo, ajusta props si necesario |
| Tipo `LayoutConfig` requiere `sheetState` que no aplica en page mode | Adaptar `WidgetRenderer` para usar `'page'` como state name o ignorar |
| Datos del backend faltan en frontend types | Wave 1a actualiza `frontend/src/api/types.ts` con tipos nuevos |
| Tests existentes de AdminDashboardPage rompen | Wave 3 elimina/reescribe tests obsoletos; tests existentes de widgets se mantienen verdes |
| Compilación TypeScript falla por unused imports tras refactor | Verificación Wave 4 corre `npm run build` (tsc -b strict) |
