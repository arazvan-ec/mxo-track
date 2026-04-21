---
type: feature
tags: [dashboard, widget]
files_touched: [backend/src/Service/AdminMetricsService.php, backend/tests/Unit/Service/AdminMetricsServiceTest.php, docs/superpowers/plans/2026-04-13-dashboard-widget-expansion.md, docs/superpowers/specs/2026-04-13-dashboard-widget-expansion-design.md, frontend/src/api/types.ts, frontend/src/components/bottom-sheet/WidgetRenderer.tsx, frontend/src/components/widgets/CollapsibleWidget.tsx, frontend/src/pages/admin/AdminDashboardPage.tsx]
patterns: []
outcome: null
outcome_verified_at: null
regressions_later: []
pr_number: null
estimated_lines: null
actual_lines: null
duration_minutes: null
consulted_in_future: []
---

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

## Retrospective

### Qué funcionó

- **Dos subagents Explore consecutivos** redujeron el ruido de contexto. El primero
  descubrió que el dashboard visible era React (no Twig), el segundo encontró
  `CollapsibleWidget` ya existente + `WidgetRenderer` + falta de infra de user prefs.
  Sin esos dos pasos habría implementado contra el dashboard equivocado.
- **TDD estricto en `AdminMetricsService`** — el test RED capturó exactamente los 8
  campos que faltaban, y la implementación fue mecánica después. Zero iteración, zero
  bugs en los nuevos campos.
- **Push-back de alcance** — cuando el usuario pidió "migración completa + todos los
  widgets + user prefs en perfil", calculé ~1200 líneas y propuse faseo en 3 PRs.
  El usuario aceptó opción conservadora. Esto evitó un plan >15 pasos que no
  sobreviviría a una compactación, y mantuvo el blast radius de este PR limitado.

### Qué salió mal

- **Verificación inicial falló por ambiente no preparado:** `make lint` se intentó
  desde `backend/` en vez de raíz, y `npm run build` falló porque `node_modules` no
  existía. Tuve que lanzar `composer install` y `npm install` mid-verificación. No es
  culpa del código pero sí del proceso — idealmente verificar deps antes de impl.
- **Deviación de spec sin preguntar:** añadí el prop `summary` a `CollapsibleWidget`
  durante implementación porque identifiqué que el spec original contradecía el
  requisito del usuario ("info visible siempre"). Documentado arriba en deviaciones,
  pero idealmente detectado en brainstorming.
- **Fallo pre-existente no relacionado:** `GitLogReaderTest` falla en este branch y
  en `main` (6/667 tests históricamente rojos según el spec, 1 hoy). No es de esta
  PR pero deja ruido en la verificación.

### Estimación vs realidad

- **Estimado:** ~400 líneas, 1 sesión, 4 tareas en 3 waves.
- **Real:** 1113 insertions / 187 deletions (9 archivos). 4 tareas completadas como
  planeadas, sin blockers reales. Estimación bastante precisa — el salto de líneas
  es inflado por docs (spec + plan + execution-log ≈ 400 líneas de markdown) y por
  el rewrite completo de `AdminDashboardPage.tsx` con la extensión del `summary`.

### Gap de proceso detectado

Mi spec inicial decía "el número grande pasa al cuerpo para no duplicar información".
Esto es un **anti-patrón** cuando el widget está colapsado — el usuario pierde info
al colapsar (peor que antes). El checklist de brainstorming no tiene un paso
explícito de "probar mentalmente el estado COLAPSADO", solo el expandido.

**Acción diferida:** para futuras features con componentes colapsables, incluir en la
revisión del spec la pregunta "¿qué información se pierde al colapsar? ¿es
aceptable?". No amerita modificar `CLAUDE.md` todavía (incidente único), pero si
reaparece en otro execution log → graduar a regla del módulo `ui-frontend.md`.

### Pattern-wide check

Consumidor único de `CollapsibleWidget` fuera de este cambio: `WidgetRenderer.tsx:
30-36`. Los widgets que renderiza (`DashboardKpisWidget`, `SystemHealthWidget`, etc.)
pierden TODA la info al colapsar — **mismo problema potencial**. Cuando migremos en
PR 2, evaluar pasar `summary` a través del registry (render-prop en
`WidgetDefinition`).

### Backlog derivado

- PR 2: seed `admin_dashboard` layout + refactor a `usePageLayout` + `WidgetRenderer`.
  Considerar exponer `summary` prop al registry.
- PR 3: `UserPreference` entity + endpoint + `ProfilePage` con selector modo b/c.
- `GitLogReaderTest` pre-existente — fix independiente si bloquea CI.
