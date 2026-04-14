# Design Spec — Cierre de gaps de retrospectiva

**Fecha:** 2026-04-14
**Branch:** `claude/enhance-dashboard-widgets-sxseH`
**Origen:** retrospective de `docs/superpowers/execution-logs/2026-04-13-dashboard-widget-expansion.md:108-171`
**Contexto delimitado:** Pragmático (procesos + UI registry).

## Problema

La retrospectiva del PR 1 identificó 5 gaps. Cerrarlos antes de PR 2 para no
acumular deuda de proceso.

## Existing Functionality Inventory

| Elemento | Decisión | Justificación |
|---|---|---|
| `backend/bin/preflight.sh` | Transformar | Añadir check de `frontend/node_modules` |
| `CLAUDE.md` (root) | Transformar | Añadir regla: pausa antes de modificar componentes compartidos |
| `.claude/test-baseline.txt` | Crear | No existe; mecanismo ya soportado en `preflight.sh:55-66` |
| `docs/knowledge/ui-frontend.md` | Transformar | Guideline "collapsible UX" |
| `frontend/src/widgets/types.ts` | Transformar | Campo `summaryComponent` en `WidgetRegistryMeta` |
| `frontend/src/widgets/registry.ts` | Transformar | Referenciar summaryComponents |
| `frontend/src/components/bottom-sheet/WidgetRenderer.tsx` | Transformar | Renderizar summary |
| 6 de 8 widgets colapsables | Transformar | Exportar `*WidgetSummary` |
| `GitLogReaderTest.php` | **Omitir** | Fix del test es un bug independiente, merece su propio PR |
| `.claude/hooks/session-start.sh` | Omitir | Preflight sigue manual |

## Decisiones

### Gap 1 — Preflight no valida `frontend/node_modules`

Añadir a `preflight.sh` un check entre "PHP Lint" y "Unit Tests":

```bash
echo "▸ Frontend deps"
if [ -d "$REPO/frontend/node_modules" ]; then
  check "frontend/node_modules present" "0"
else
  check "frontend/node_modules missing — run: cd frontend && npm install" "1"
fi
```

### Gap 2 — Deviación de spec en componentes compartidos

Añadir a `CLAUDE.md` sección "Doing tasks" (después del bloque de SOLID) esta regla:

> **Shared component modifications require returning to brainstorming.** If
> mid-implementation you need to modify a component consumed by more than one
> file (e.g., a UI primitive, a base class, a registry schema), STOP. Do not
> edit. Update the spec with the new requirement, re-enter brainstorming with
> the user, get approval, then continue. This prevents silent API extensions
> from shipping without review.

Ejemplo real (del PR 1): `CollapsibleWidget.summary` prop fue añadido mid-impl
sin pasar por brainstorming. Documentado en retrospective pero el proceso
permitió el bypass. Esta regla lo cierra.

### Gap 3 — Baseline para tests pre-existentes

Crear `.claude/test-baseline.txt` con contenido `1`. Corresponde al fallo
conocido de `GitLogReaderTest::testGetCommitsReturnsStructuredArray`.
`preflight.sh:55-66` ya lee este archivo; solo falta crearlo.

### Gap 4 — Guideline collapsible UX

Añadir sección en `docs/knowledge/ui-frontend.md`:

> ### Collapsible components UX
>
> When designing a component that can collapse (e.g. widgets wrapped in
> `CollapsibleWidget`), explicitly enumerate what information disappears
> when the component is collapsed. For each piece of lost info, decide:
>
> 1. **Keep visible** — promote to the summary/header slot (`summary` prop
>    on `CollapsibleWidget`, `summaryComponent` in `WidgetRegistryEntry`)
> 2. **Accept loss** — the info is non-critical and discoverable by expanding
> 3. **Don't collapse** — if too much critical info would vanish, the
>    component shouldn't be collapsible at all
>
> Anti-pattern: shipping a collapsible widget where the collapsed state
> shows only the title. Users lose all actionable info by clicking collapse,
> worse than no-collapse-at-all.

### Gap 5 — Pattern-wide: extender registry con summaryComponent

**5a — Infra:**

1. `types.ts`:
   ```ts
   import type { ComponentType } from 'react';
   export interface WidgetRegistryMeta {
     collapsible?: boolean;
     sectionTitle?: string;
     /** Optional summary component rendered in the CollapsibleWidget header */
     summaryComponent?: ComponentType<WidgetProps>;
   }
   ```

2. `WidgetRenderer.tsx`: cuando envuelve con `CollapsibleWidget`, calcular
   `summary` desde `entry.summaryComponent`:
   ```tsx
   const Summary = entry.summaryComponent;
   return (
     <CollapsibleWidget
       ...
       summary={Summary ? <Summary data={pageData} expanded={expanded} /> : undefined}
     >
   ```

3. `registry.ts`: importar y referenciar los 6 summary components.

**5b — Summaries por widget:**

| Widget | Summary component exporta |
|---|---|
| `DashboardKpisWidget` | `DashboardKpisSummary` — "N rutas · M paradas" |
| `SystemHealthWidget` | `SystemHealthSummary` — badge "N/M OK" |
| `InfrastructureMetricsWidget` | `InfrastructureMetricsSummary` — "hace Xs" |
| `MiniReportsWidget` | `MiniReportsSummary` — "N entregas 7d" |
| `CustomerKpisWidget` | `CustomerKpisSummary` — "X entregas hoy" |
| `CustomerOptimizationWidget` | `CustomerOptimizationSummary` — "X% ahorro" |
| `ActivityFeedWidget` | **omitir** — contenido live, sin summary útil |
| `ReportsBannerWidget` | **omitir** — CTA, no tiene datos |

Cada summary component es un export hermano en el mismo archivo del widget,
consume el mismo `WidgetProps` (`data: unknown`), y hace cast interno al shape
que su widget principal ya maneja.

## Omission Decisions

| Elemento | Decisión | Justificación |
|---|---|---|
| Fix de `GitLogReaderTest` | Omitir | Bug independiente, merece PR separado |
| Auto-run preflight en session-start | Omitir | Fuera de alcance; preflight manual es suficiente por ahora |
| Summaries para `ActivityFeed` y `ReportsBanner` | Omitir | No hay contenido que resumir |
| Aplicar regla de Gap 2 retroactivamente (revertir `summary` de `CollapsibleWidget`) | Omitir | El cambio ya fue aprobado implícitamente en PR 1 merge |

## Criterios de aceptación

1. `bash backend/bin/preflight.sh` detecta `frontend/node_modules` faltante.
2. `CLAUDE.md` contiene la regla de shared component modifications.
3. `.claude/test-baseline.txt` existe con `1`.
4. `docs/knowledge/ui-frontend.md` tiene sección "Collapsible components UX".
5. `WidgetRenderer` renderiza summary cuando el widget lo declara.
6. Los 6 widgets listados exportan `*Summary`.
7. `phpunit`: ≤ baseline. `npm run build`: verde.
