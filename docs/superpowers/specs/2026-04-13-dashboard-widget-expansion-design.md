# Design Spec — Dashboard Widget Expansion (PR 1 of 3)

**Fecha:** 2026-04-13
**Branch:** `claude/enhance-dashboard-widgets-sxseH`
**Contexto delimitado:** Pragmático (UI admin) — sin DDD puro.
**Alcance:** Primera fase del roadmap acordado (ver "Fases futuras" al final).

## Problema

La página `AdminDashboardPage` muestra 4 KPI cards y otros widgets con una única cifra
cada uno (ej. "Rutas activas: 1"). El usuario pidió:

1. Añadir información adicional visible en cada widget (ej. "Rutas: 55, Rutas activas: 1").
2. Capacidad de expandir cada widget para ver más detalle.

## Decisión

Reusar el componente existente `CollapsibleWidget` (`frontend/src/components/widgets/
CollapsibleWidget.tsx`) envolviendo cada widget visible de `AdminDashboardPage`, y
extender `AdminMetricsService` con los totales y desgloses necesarios para poblar el
cuerpo expandido.

**Mantiene la página hardcoded** (sin migración al widget-registry) — esa migración
queda para PR 2.

## Existing Functionality Inventory

| Elemento | Decisión | Justificación |
|---|---|---|
| `AdminDashboardPage.tsx` 4 KPI cards inline | **Transformar** | Envolver cada uno en `CollapsibleWidget` con cuerpo expandido |
| `SISTEMA` widget (6 RadialGauge) | **Transformar** | Envolver + añadir lista de servicios con latencia |
| `Entregas (7 días)` bar chart | **Transformar** | Envolver (ya tiene detalle completo) |
| `Top transportistas` | **Transformar** | Envolver (ya tiene detalle completo) |
| Fila infraestructura (Posiciones/DB/Ingestion) | **Transformar** | Envolver el bloque completo, contenido actual pasa a cuerpo |
| `Reportes y Analítica` banner | **Omitir** | Es un link de navegación, no un widget de datos |
| `CollapsibleWidget` component | **Incluir (reusar)** | Tiene persistencia localStorage, transición CSS, icon rotation |
| `AdminMetricsService.collect()` | **Transformar** | Añadir campos nuevos sin romper los existentes |
| `DashboardMetrics` TS type | **Transformar** | Extender interface en `api/types.ts` |
| Widget registry (`WidgetRenderer` + `registry.ts`) | **Omitir** | Reservado para PR 2 (migración a registry) |
| Infraestructura de user preferences | **Omitir** | Reservado para PR 3 |

## Diseño

### Backend — extensión de `AdminMetricsService`

Nuevos campos en el diccionario devuelto por `collect()`:

```php
[
    // Existentes (no tocar)
    'import_runs_today' => int,
    'positions_ingested_last_hour' => int,
    'active_routes' => int,
    'pending_stops' => int,
    // Nuevos
    'total_routes' => int,                         // COUNT(route_plan)
    'total_stops' => int,                          // COUNT(route_stop)
    'route_status_breakdown' => array<string,int>, // GROUP BY status en route_plan
    'stop_status_breakdown' => array<string,int>,  // GROUP BY status en route_stop
    'deliveries_today' => int,                     // COUNT(route_stop) con status IN (DELIVERED) y updated_at >= today
    'failed_today' => int,                         // COUNT(route_stop) con status='FAILED' y updated_at >= today
    'import_runs_last_7d' => int,                  // COUNT(csv_import_run) últimos 7 días
    'positions_last_24h' => int,                   // COUNT(vehicle_positions) últimas 24h
]
```

**Razón del breakdown:** la UI expandida muestra "ACTIVE: 1, PLANNED: 3, COMPLETED: 51"
sin hardcodear la lista de status en el frontend. Defensivo ante nuevos estados.

**Patrón del query para breakdowns:**

```php
private function countByStatusGroup(string $table): array
{
    $rows = $this->connection->fetchAllAssociative(
        sprintf('SELECT status, COUNT(*) AS c FROM %s GROUP BY status', $table),
    );
    $out = [];
    foreach ($rows as $row) {
        $out[(string) $row['status']] = (int) $row['c'];
    }
    return $out;
}
```

### Frontend — extensión de `DashboardMetrics` type

En `frontend/src/api/types.ts`:

```ts
export interface DashboardMetrics {
  // Existentes
  active_routes: number;
  pending_stops: number;
  import_runs_today: number;
  positions_ingested_last_hour: number;
  // Nuevos
  total_routes: number;
  total_stops: number;
  route_status_breakdown: Record<string, number>;
  stop_status_breakdown: Record<string, number>;
  deliveries_today: number;
  failed_today: number;
  import_runs_last_7d: number;
  positions_last_24h: number;
}
```

### Frontend — refactor de `AdminDashboardPage.tsx`

**Patrón por widget KPI:**

```tsx
<CollapsibleWidget
  title="Rutas activas"
  storageKey="mxo-dashboard-widget-routes-minimized"
  defaultExpanded={true}
>
  {/* Summary siempre visible dentro del cuerpo expandido */}
  <div>
    <AnimatedCounter value={metrics.active_routes} ... />
    <p>de <span>{metrics.total_routes}</span> totales</p>
  </div>
  {/* Detalle extra */}
  <div>
    <h3>Desglose por estado</h3>
    <ul>
      {Object.entries(metrics.route_status_breakdown).map(([status, count]) => (
        <li key={status}>{status}: {count}</li>
      ))}
    </ul>
  </div>
</CollapsibleWidget>
```

**Header visual:** el header del `CollapsibleWidget` queda minimal (título + chevron).
El número grande (AnimatedCounter) pasa al cuerpo para no duplicar información entre
header y cuerpo cuando está expandido. Cuando está colapsado, solo se ve el título —
esto es intencional: el usuario hace click para ver datos.

**Alternativa evaluada (descartada):** dejar el número grande en el header. Rechazado
porque `CollapsibleWidget` ya renderiza un header fijo con estilo `<button>` (ver
`CollapsibleWidget.tsx:46-68`); meter contenido custom en ese header requeriría
refactor del componente, fuera de alcance de PR 1.

**Layout:** el bento grid actual (3 filas, `grid-cols-1 lg:grid-cols-3`) se conserva.
Cada celda contiene un `CollapsibleWidget` en lugar de un `div.theme-card`.

**Animaciones:** se pierden las animaciones `animate-fade-in-up` escalonadas dentro
de `CollapsibleWidget` (éste ya tiene su propia transición max-height). Aceptable
para PR 1; si hay regresión de UX perceptible se revisa en capture.

### Persistencia del estado colapsado

`CollapsibleWidget` ya persiste en localStorage con la key `storageKey` que le pasemos.
Un key por widget. Scheme propuesta:

```
mxo-dashboard-widget-routes-minimized
mxo-dashboard-widget-stops-minimized
mxo-dashboard-widget-imports-minimized
mxo-dashboard-widget-positions-minimized
mxo-dashboard-widget-system-minimized
mxo-dashboard-widget-deliveries-minimized
mxo-dashboard-widget-top-drivers-minimized
mxo-dashboard-widget-infra-minimized
```

## Omission Decisions

| Elemento | Decisión | Justificación |
|---|---|---|
| Migración a widget-registry (`WidgetRenderer`) | Omitir | Reservado para PR 2 |
| Creación de entidad `UserPreference` | Omitir | Reservado para PR 3 |
| Endpoint PATCH `/api/me/preferences` | Omitir | Reservado para PR 3 |
| `ProfilePage` para selector b/c | Omitir | Reservado para PR 3 |
| Gráficos/sparklines en cuerpo expandido | Omitir | Diferible; la info numérica cubre el requisito mínimo |
| Migrar `OperatorDashboardController` Twig | Omitir | El usuario ve la página React como admin; Twig fuera de alcance |

## Trade-offs y riesgos

**Riesgo bajo:**
- `AdminMetricsService` ya usa DBAL directo, añadir queries sigue el mismo patrón.
- `CollapsibleWidget` es un componente probado (PR 248).
- No hay migración de base de datos.

**Riesgo medio:**
- `AdminDashboardPage.tsx` cambia ~200 líneas — potencial regresión visual. Mitigación:
  `npm run build` fresh + revisión visual manual documentada en capture.

**Riesgos descartados:**
- Performance: las nuevas queries son COUNTs sobre columnas con índice
  (`route_plan.status`, `route_stop.status` ya filtrados en el código existente).
- Concurrencia: `collect()` se llama cada 30s desde el hook; añadir 4 queries más
  no cambia el patrón.

## Criterios de aceptación (PR 1)

1. `GET /api/admin/dashboard` devuelve los 8 campos nuevos en `metrics`.
2. La página muestra cada uno de los 7 widgets envueltos en `CollapsibleWidget`.
3. Click en el header colapsa/expande y persiste la preferencia en localStorage.
4. Para Rutas, el cuerpo expandido muestra el desglose por status.
5. Para Paradas, el cuerpo expandido muestra el desglose por status + entregadas hoy.
6. `make lint` verde.
7. `php vendor/bin/phpunit` — tests de `AdminMetricsService` pasan (nuevos + existentes).
8. `cd frontend && npm run build` verde.

## Fases futuras (NO implementar en este PR)

- **PR 2 — Migración a widget-registry:**
  Seed `admin_dashboard` layout en `PageLayout`, asociar widgets existentes del registry,
  refactor `AdminDashboardPage` para usar `usePageLayout('admin_dashboard')` +
  `WidgetRenderer` en modo `'page'`.

- **PR 3 — Preferencia de usuario para estado inicial:**
  Entidad `UserPreference` con JSON `widget_preferences`, migración Doctrine, endpoint
  GET/PATCH `/api/me/preferences`, `ProfilePage` con selector "modo b (colapsado) vs
  modo c (header-only visible + expandible)", hook `useUserPreferences` que alimenta
  `CollapsibleWidget.defaultExpanded`.
