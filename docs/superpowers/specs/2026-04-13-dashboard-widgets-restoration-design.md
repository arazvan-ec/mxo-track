# Spec — 2026-04-13 — Restauración del Widget System en Dashboard Admin + Expansión Granular

**Autor:** Claude · **Branch:** `claude/enhance-dashboard-widgets-R46AJ`

## Problema

`AdminDashboardPage.tsx` es un componente monolítico de 360 líneas que renderiza
inline 4 KPI cards, 6 RadialGauges del sistema, bar chart de 7 días, top drivers,
3 cards de infraestructura y un banner. La migración al widget system del 2026-04-08
(commit `1394a79`) fue reemplazada por un rediseño "innovative-dashboard-design"
(PRs #221/#222) que perdió:

1. **Widget system** — sistema de widgets reutilizables registrados, con propiedades
   `collapsible` y `expanded` que ya funciona en otras vistas (FleetMapPage,
   RouteDetailPage, RouteAnalysisPage, OperatorDashboardPage).
2. **Persistencia de estado por widget** (localStorage minimize/expand).
3. **Capacidad de configurar layouts** vía `PageLayoutEditorPage`.

El usuario quiere restaurar el widget system **en todas las vistas** (decisión
explícita de arquitectura), añadir más información a cada widget y permitir
expansión granular de cada uno (similar al patrón `expanded` del bottom-sheet
en RouteDetailPage).

## Objetivos

- Migrar `AdminDashboardPage` al widget system (`<WidgetRenderer mode='page' />`)
- Cada widget admite **dos toggles independientes**:
  - **Minimize ▼** (existente): oculta todo el contenido, deja header visible
  - **Detail ⤢** (nuevo): cambia entre vista compacta y detallada del contenido
- Cada widget muestra información significativamente mayor que el dashboard actual
- Backend extiende `AdminMetricsService` con datos agregados y series temporales
- Componentes de gráfica reutilizan los existentes (`SparklineSVG`, `RadialGauge`,
  `AnimatedCounter`, `AnimatedBarChart`, `MetricPairs`, `RouteProgressBar`)
- Phase 1 (v0): todo lo que se puede consultar con queries simples + sparklines en memoria
- Phase 2 (Mature): nuevas tablas/cron para uptime histórico e infra avanzada

## No Objetivos

- No reemplazar `LayoutConfig` editable — el layout admin queda hardcoded en
  esta iteración (el editor sigue funcionando para otras páginas)
- No tocar el dashboard de cliente (`CustomerDashboardPage`) — fuera de scope
- No migrar `OperatorDashboardPage` — ya usa widget system
- No introducir librería de charts externa (Chart.js, Recharts) — reutilizar
  componentes SVG ya construidos

## Existing Functionality Inventory

| Elemento | Decisión | Justificación |
|---|---|---|
| AdminDashboardPage.tsx (360 líneas inline) | **Transform** — reescribir a ~80 líneas | Restaurar widget system perdido en PR #221/#222 |
| Bento grid actual (KPIs 2/3 + Sistema 1/3, Entregas 3/5 + Drivers 2/5) | **Omit** — sustituir por stack vertical de widgets | Decisión explícita del usuario: widget system universal |
| `getGreeting()`, `formatDate()`, `formatSecondsAgo()` | **Include** — mantener inline en header | Helpers locales del header de bienvenida |
| `SERVICE_CONFIG` (6 servicios) | **Transform** — mover a `SystemHealthWidget` | Es config del widget de sistema |
| `theme-card` styling con `animation-delay` | **Include** — los widgets usan `theme-card` por defecto | Mantener look-and-feel |
| `DashboardKpisWidget` (existe, sin uso) | **Transform** — añadir compact/expanded modes + breakdown | Widget base ya registrado |
| `SystemHealthWidget` (existe) | **Transform** — añadir modo expanded con sparklines | Reutilizar |
| `MiniReportsWidget` (existe) | **Transform** — añadir modo expanded con tabla, top 10, sparkline 30d, etc. | Reutilizar |
| `InfrastructureMetricsWidget` (existe) | **Transform** — añadir modo expanded con desgloses por tabla | Reutilizar |
| `ActivityFeedWidget` (existe) | **Include** — minimizado por defecto | Ya OK |
| `ReportsBannerWidget` (existe) | **Include** — sin cambios | Ya OK |
| `CollapsibleWidget` (frontend/src/components/widgets/) | **Transform** — añadir prop `supportsDetail` + segundo botón ⤢ | Patrón nuevo de doble toggle |
| `WidgetRenderer` | **Transform** — pasar `expanded={detailed}` cuando widget tiene detail toggle | Bridge entre toggle y prop del widget |
| `WidgetProps.expanded` | **Include** — semántica: "vista detallada vs compacta" | Ya existe el prop |
| `AdminMetricsService::collect()` | **Transform** — añadir agregados, breakdowns, series 7-30d | Backend para nuevos datos |
| `ReportingService` | **Include** — usar métodos existentes (getDeliveryReport, getDriverPerformance, getTrendData) | Ya tiene aggregations |
| `RoutePerformanceMetricRepository` | **Include** — usar `getCustomerAggregateMetrics()` | Ya tiene km/min saved |
| Tabla nueva `system_health_log` | **Add (Phase 2)** | Para uptime histórico e incidentes |
| Cron de muestreo health | **Add (Phase 2)** | Job Symfony Scheduler cada 60s |

## Omission Decisions

| Elemento | Decisión | Justificación |
|---|---|---|
| Bento grid layout actual (KPIs 2/3 + Sistema 1/3, Entregas 3/5 + Drivers 2/5) | **Omit** — sustituir por stack vertical via WidgetRenderer | Decisión explícita del usuario: priorizar widget system universal sobre layout artístico. El bento perdería sentido al introducir doble toggle por widget. |
| `theme-card-overlay` para widgets sobre mapa | **Omit** — no aplica a este dashboard (no hay mapa de fondo) | El dashboard admin no tiene background map. Otros widgets que sí lo usan (KpiPills, MetricPairs en FleetMapPage) mantienen su clase. |
| Floating ThemeSwitcher en AdminDashboardPage | **Omit** — ya removido en commit 58de2d5 (movido a TopBar inline) | Sin cambios en este PR. |
| `CustomerDashboardPage` migration | **Omit** — fuera de scope, queda en TODO | El usuario indicó "todas las vistas" como meta a largo plazo, pero este PR se enfoca en admin. CustomerDashboard ya usa `customer_kpis` y `customer_optimization` widgets parcialmente. |
| Layout configurable via `LayoutConfig` editor | **Omit** — layout admin queda hardcoded en este PR | El editor existente (`PageLayoutEditorPage`) sigue funcionando para otras páginas. Migrar admin al editor añadiría 1-2 waves más sin valor inmediato para el usuario. |
| Sparklines reales de health (60min history) | **Omit (Phase 2)** — requiere tabla `system_health_log` y cron | En Phase 1 los sparklines del SystemHealthWidget detailed quedan vacíos o muestran solo la última lectura. |
| Métricas avanzadas de Infra (queue depth, cache hit, disk free, table sizes) | **Omit (Phase 2)** — requieren queries especializadas | InfrastructureMetricsWidget detailed muestra solo las métricas alcanzables con queries actuales en Phase 1. |

## Diseño

### 1. Doble toggle en CollapsibleWidget

Extender `CollapsibleWidget` con prop opcional `supportsDetail`:

```tsx
interface CollapsibleWidgetProps {
  title: string;
  icon?: ReactNode;
  storageKey: string;          // base key — sufijos se añaden internamente
  defaultExpanded?: boolean;   // default minimize state (true = visible)
  supportsDetail?: boolean;    // si true, renderiza segundo botón ⤢
  defaultDetailed?: boolean;   // default detail mode
  onDetailChange?: (detailed: boolean) => void;
  children: ReactNode | ((detailed: boolean) => ReactNode);  // soporta render-prop
}
```

Storage keys:
- `mxo-dashboard-widget-{type}-minimized` (existente)
- `mxo-dashboard-widget-{type}-detailed` (nuevo)

Header:
```
┌──────────────────────────────────────────────┐
│ 🚛 RUTAS                          ⤢   ▼      │
└──────────────────────────────────────────────┘
```
- ⤢ → toggle compact/detailed
- ▼ → toggle hide/show (chevron rota a 180° cuando visible)

`WidgetRenderer` cuando recibe widget con `collapsible: true` y `supportsDetail: true`
en su entry del registry, pasa una función render-prop a `CollapsibleWidget` que
inyecta `expanded={detailed}` al widget interno.

### 2. Layout admin dashboard

Hardcoded en `AdminDashboardPage`:

```tsx
const ADMIN_DASHBOARD_LAYOUT: LayoutConfig = {
  widgets: {
    page: [
      { type: 'dashboard_kpis', position: 0 },
      { type: 'system_health', position: 1 },
      { type: 'mini_reports', position: 2 },
      { type: 'infrastructure_metrics', position: 3 },
      { type: 'activity_feed', position: 4 },
      { type: 'reports_banner', position: 5 },
    ],
  },
};
```

`SheetStateName` para mode='page' usa `'page'` o adapta `WidgetRenderer` para
ignorar sheetState en page mode.

### 3. Backend — `AdminMetricsService` extendido

```php
public function collect(): array
{
    return [
        'meta' => [
            'generated_at' => $now->format(DateTimeInterface::ATOM),
            'today_start' => $todayStart,
        ],
        'routes' => $this->routesData(),
        'stops' => $this->stopsData(),
        'imports' => $this->importsData(),
        'positions' => $this->positionsData(),
        'system' => $this->systemData(),     // Phase 2 enriched
        'deliveries' => $this->deliveriesData(),
        'infrastructure' => $this->infrastructureData(),

        // BACK-COMPAT (legacy contract) — campos planos que clientes existentes ya consumen
        'active_routes' => $routes['by_status']['ACTIVE'] ?? 0,
        'pending_stops' => $stops['by_status']['PENDING'] ?? 0,
        'import_runs_today' => $imports['today_count'],
        'positions_ingested_last_hour' => $positions['last_hour_count'],
    ];
}
```

Estructura por bloque:

```php
'routes' => [
    'today_count' => 55,
    'by_status' => ['PLANNED'=>12, 'ACTIVE'=>1, 'DONE'=>32, 'CANCELLED'=>10],
    'completion_rate' => 58.2,           // DONE / today_count * 100
    'km_saved_today' => 142.7,
    'min_saved_today' => 327,
    'avg_savings_percent' => 18.5,
    'plan_accuracy_avg' => 87.3,
    'completed_last_7_days' => [4, 8, 12, 5, 9, 7, 32],   // bar chart
    'km_saved_last_14_days' => [120, ..., 142],            // sparkline
    'success_rate_last_30_days' => [94, ..., 98],          // sparkline
    'top_drivers_today' => [
        ['driver_name' => 'A. Razvan', 'completed' => 8],
        ...
    ],
    'top_customers_today' => [
        ['customer_name' => 'Cliente X', 'count' => 12],
        ...
    ],
    'delayed_now' => [
        ['route_public_id' => '01HX...', 'driver_name' => 'X', 'overdue_min' => 23],
        ...
    ],
],
```

Análogo para `stops`, `imports`, `positions`, `deliveries`, `infrastructure`.

`system` en Phase 1 mantiene compat (latency_ms por servicio); en Phase 2
añade:
```php
'system' => [
    'services' => [
        'database' => [
            'ok' => true,
            'latency_ms' => 6,
            'latency_history_60min' => [5, 6, 5, ..., 6],   // 60 puntos
            'uptime_24h_pct' => 100.0,
            'last_incident_at' => null,
        ],
        ...
    ],
    'all_healthy' => true,
    'degraded_services' => [],
],
```

### 4. Phase 2: persistencia de health checks

**Migración** `Version20260413000000`:
```sql
CREATE TABLE system_health_log (
    id BIGSERIAL PRIMARY KEY,
    service VARCHAR(32) NOT NULL,
    ok BOOLEAN NOT NULL,
    latency_ms INT,
    sampled_at TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_system_health_service_time ON system_health_log (service, sampled_at DESC);
```

**Cron Symfony Scheduler** (`HealthSamplerSchedule`) cada 60s:
- Ejecuta `SystemHealthService::checkLive()`
- Inserta una fila por servicio
- Job Messenger async para no bloquear

**Retention:** comando `system:health:purge` purga registros > 7 días (cron diario).

### 5. Diseño visual de cada widget

#### DashboardKpisWidget

**Compact (default):** 4 cards en grid 1/2/4 cols (igual que ahora) con número + label + icon + bottom accent bar.

**Detailed:** cada card crece y muestra:

- **Rutas**: número + 4 mini-pills horizontales (Plan/Act/Done/Canc) · `MetricPairs` antes/después · 7-day bar · 2 RadialGauges (% completado · % éxito) · top 3 drivers con barra · sparkline km 14d · lista rutas con retraso (alerta)
- **Paradas**: número + 3 mini-pills (Pend/Deliv/Excep) · 2 RadialGauges (% entregadas · ETA accuracy) · 12h hourly bar · top 5 razones excepción · sparkline éxito 14d · lista retrasos · top 5 clientes · próximas 5 ETAs
- **Imports CSV**: número + 3 mini-pills (OK/Fail/Proc) · 7-day bar · RadialGauge % filas válidas · counter total filas · lista últimos 5 imports · top 3 fuentes · "hace X min" · top 3 errores
- **Posiciones**: número + delta vs hora anterior · sparkline 24h · counter vehículos online · RadialGauge % online · latencia avg · top 3 vehículos · lista vehículos sin reportar · velocidad avg

Cada KPI card es un componente reutilizable `<KpiCardExpandable>` con prop `expanded`.

#### SystemHealthWidget

**Compact:** 6 RadialGauges con label (igual que actual).

**Detailed:** cada servicio tiene una fila con: nombre · gauge grande · sparkline latencia 60min · % uptime 24h · max/avg/min · "última caída hace X" · estado degradado.

#### MiniReportsWidget

**Compact:** bar chart 7d + total + top 5 drivers (igual que actual).

**Detailed:** añade abajo:
- KPI row: total mes + delta % vs mes anterior · RadialGauge % éxito 7d · counter total
- Tabla 7d con columnas: fecha · total · éxito · fallidas · % éxito
- Top 10 drivers (vs 5)
- Top 5 clientes
- 3 razones de fallo más comunes
- Sparkline tendencia 30d
- Counter tiempo promedio POD
- Bar chart distribución horaria 24h

#### InfrastructureMetricsWidget

**Compact:** 3 cards (positions, DB size, last ingestion).

**Detailed:** añade:
- Sparkline crecimiento DB 30d
- Tabla top 5 tablas por tamaño + fila count
- Edad datos más antiguos en `vehicle_positions`
- Sparkline tasa ingestión 24h (puntos/min)
- Counter Messenger queue depth (Phase 2)
- Counter cache hit ratio Redis (Phase 2)
- RadialGauge disco disponible (Phase 2)

#### ReportsBannerWidget

Sin cambios. No `supportsDetail`.

#### ActivityFeedWidget

Minimizado por defecto (current) + `supportsDetail`: compact 10 últimas, detailed 50 últimas.

### 6. Layout final del dashboard (vertical stack)

```
┌──────────────────────────────────────────────┐
│ Buenos días, admin                           │
│ martes, 14 abril 2026 · Actualizado hace 2s  │
└──────────────────────────────────────────────┘
┌─ KPIs ─────────────────────────────── ⤢  ▼ ──┐
│ [Rutas] [Paradas] [Imports] [Posiciones]     │
└──────────────────────────────────────────────┘
┌─ Estado del sistema ──────────────── ⤢  ▼ ──┐
│ 6 RadialGauges + (detailed: sparklines)      │
└──────────────────────────────────────────────┘
┌─ Reportes ────────────────────────── ⤢  ▼ ──┐
│ Bar chart 7d + Top drivers + (detailed)      │
└──────────────────────────────────────────────┘
┌─ Infraestructura ─────────────────── ⤢  ▼ ──┐
│ 3 cards + (detailed: tablas + sparklines)    │
└──────────────────────────────────────────────┘
┌─ Reportes y Analítica ──────────────────── ──┐
│ Banner CTA                                   │
└──────────────────────────────────────────────┘
┌─ Actividad en vivo ──────────── (minimized) ─┐
└──────────────────────────────────────────────┘
```

## Phasing

### Phase 1 (v0) — En este PR

- Doble toggle `CollapsibleWidget` + render-prop API
- Backend `AdminMetricsService` extendido con todos los datos no-uptime
  (queries simples + agregaciones existentes)
- 4 widgets refactorizados con compact/detailed (KPIs, MiniReports, Infrastructure parcial)
- SystemHealthWidget detailed con datos disponibles (sin uptime histórico ni
  sparkline: solo última lectura — Phase 2 lo enriquece)
- AdminDashboardPage refactorizada a `<WidgetRenderer mode='page' />`
- Tests: backend service tests + 1 test por widget (compact + detailed)

### Phase 2 (Mature) — Siguiente PR (no en este)

- Tabla `system_health_log` + migración + entity
- HealthSamplerSchedule cron + handler async
- Comando purga
- Enriquecer `SystemHealthWidget` detailed con sparklines reales y uptime
- Infraestructura: queries de tabla sizes (pg_total_relation_size), retention
  age, queue depth (Messenger transports), cache hit Redis (INFO stats), disk
  free (df)

## Métricas de éxito

- AdminDashboardPage < 100 líneas (vs 360 actual)
- Cada widget independientemente minimizable y expandible (estado en localStorage)
- Tests verdes: `cd backend && php vendor/bin/phpunit` y `cd frontend && npm run build`
- 0 nuevos errores de TypeScript / lint
- Visual: el dashboard muestra cada widget en compact por defecto, persistencia
  de estado entre recargas

## Riesgos

- **Pérdida visual del bento grid**: aceptado por el usuario
- **Backend agregaciones lentas**: `routes_by_status` con CustomerTenantFilter — verificar
  índices existentes (`route_plan(status)`, `route_stop(status)`)
- **Layout config divergencia**: hardcoded layout en AdminDashboardPage no se sincroniza con
  `PageLayoutEditorPage` — aceptado para v0
- **Phase 2 health log volumen**: 6 servicios × 1440 muestras/día = 8640 filas/día —
  retention 7d = 60K filas, manejable con índice por (service, sampled_at)

## Decisión Log Entry

```markdown
### [2026-04-13] Restauración widget system en AdminDashboardPage tras pérdida en redesign #221/#222
- Problema: PR de redesign innovativo reemplazó la migración 04-08 al widget system con un componente monolítico de 360 líneas, perdiendo la consistencia con el resto del SPA donde el widget system es universal
- Decisión: restaurar widget system en AdminDashboardPage añadiendo además doble toggle (minimize + detail) y enriqueciendo cada widget con breakdowns y gráficas reutilizando componentes SVG existentes
- Alternativas descartadas:
  - Mantener monolítico añadiendo expansión in-place (no escalable, no consistente)
  - Crear `BentoWidgetRenderer` con grid-area por widget (alta complejidad, no resuelve consistencia)
- Resultado: (post-implementación)
```
