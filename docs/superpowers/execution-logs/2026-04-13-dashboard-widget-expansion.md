# Execution Log — 2026-04-13 — Dashboard Widget Expansion (PR 1)

**Type:** feature
**Branch:** `claude/enhance-dashboard-widgets-sxseH`
**Spec:** `docs/superpowers/specs/2026-04-13-dashboard-widget-expansion-design.md`
**Plan:** `docs/superpowers/plans/2026-04-13-dashboard-widget-expansion.md`

## Scope

PR 1 de 3 del roadmap "Dashboard widgets enriquecidos + colapsables + preferencias".
Esta PR cubre:
- Enriquecimiento de métricas backend (`AdminMetricsService`)
- Envoltura de los 7 widgets de `AdminDashboardPage` en `CollapsibleWidget`
- Persistencia de estado colapsado client-side (localStorage, ya integrada en el
  componente reusado)

Fuera de alcance (diferido a PR 2/3):
- Migración al widget-registry system
- Infraestructura de preferencias de usuario (`UserPreference`, endpoint, perfil)

## Alternativas consideradas

1. **Enriquecimiento inline sin colapsable** — rechazado, no cubre requisito "expandir".
2. **Migración completa al widget-registry** — diferido a PR 2 por alcance (>1200 líneas
   totales estimadas, plan >15 pasos, riesgo alto de compactación).
3. **`CollapsibleWidget` sin `summary` prop** — evaluado en spec, descartado tras
   repaso: el usuario pidió info visible siempre ("rutas: 55, rutas activas: 1"), así
   que ocultar el número al colapsar violaba el requisito. Extensión mínima (~8 líneas)
   justificada y backwards-compatible (`summary` es opcional, sin consumidores previos
   usándolo).

## Deviación del spec durante implementación

**Extensión del `CollapsibleWidget` con prop `summary`:** el spec original decía
mantener el componente sin cambios y mover el número al cuerpo. En implementación se
determinó que esto contradecía la intención explícita del usuario (info visible aunque
el widget esté colapsado). Se añadió un prop opcional `summary?: ReactNode` que se
renderiza dentro del header. Cambio:

- `CollapsibleWidget.tsx`: +8 líneas (prop opcional, sin romper consumidores existentes).
- Verificado: `WidgetRenderer.tsx:30-36` (único otro consumidor) no pasa `summary`, sigue
  funcionando igual.

## Cambios

### Backend

- `backend/src/Service/AdminMetricsService.php` — 8 campos nuevos:
  `total_routes`, `total_stops`, `route_status_breakdown`, `stop_status_breakdown`,
  `deliveries_today`, `failed_today`, `import_runs_last_7d`, `positions_last_24h`.
  Helpers privados: `countAll`, `countByValueSince`, `countByStatusGroup`.
- `backend/tests/Unit/Service/AdminMetricsServiceTest.php` — nuevo (4 tests, 25
  aserciones) cubriendo keys existentes, keys nuevas, parsing de breakdowns y cast a int.

### Frontend

- `frontend/src/api/types.ts` — `DashboardMetrics` extendido con 8 campos.
- `frontend/src/components/widgets/CollapsibleWidget.tsx` — prop opcional `summary`.
- `frontend/src/pages/admin/AdminDashboardPage.tsx` — refactor: 7 widgets envueltos en
  `CollapsibleWidget` (Rutas, Paradas, Imports, Posiciones, Sistema, Entregas 7d,
  Top transportistas, Infraestructura).

## Verificación

- `make lint` → ✅ verde (todos los .php en `backend/src/`)
- `php vendor/bin/phpunit` → ✅ 666/667 pasan. 1 fallo pre-existente en
  `GitLogReaderTest::testGetCommitsReturnsStructuredArray` (test que ejecuta `git log`
  del repo real y espera exactamente 3 commits; el repo tiene más). Ajeno a esta PR.
- `cd frontend && npm run build` → ✅ verde (`tsc -b && vite build`, 237 módulos,
  sin errores de tipos).
- `AdminMetricsServiceTest` → ✅ 4/4 tests nuevos pasan.

## Keys de localStorage introducidas

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

## Observaciones para PR 2/3

- La página sigue hardcoded. La migración al widget-registry (PR 2) requerirá crear
  una fila `PageLayout` para `admin_dashboard` y asociar 8 widgets (la enum ya existe
  en `backend/src/Enum/PageKey.php:17`).
- `WidgetRenderer` (`frontend/src/components/bottom-sheet/WidgetRenderer.tsx`) ya
  envuelve widgets colapsables en modo `'page'` → la migración se beneficia de la
  extensión `summary` hecha en esta PR si decidimos exponerla a los widgets del
  registry.
- Las 8 keys de localStorage deberán migrarse a preferencias de usuario en PR 3
  (o mantener localStorage como fallback si el usuario no tiene preferencia guardada).

## Deuda técnica reconocida

- Fallos visuales sutiles no probados en browser: la refactorización cambió el layout
  de los 4 KPI cards (antes 4 columnas en mobile → ahora 2 columnas con widgets más
  grandes). Decisión consciente porque los widgets colapsables con `summary` necesitan
  más ancho horizontal para mostrar título + número + chevron cómodamente. Si genera
  regresión perceptible, ajustar en PR 2.
- Animación `animate-fade-in-up` escalonada eliminada (cada KPI ahora tiene la
  animación interna de `CollapsibleWidget`). Visualmente equivalente en la práctica.
