---
type: process
tags: [route]
files_touched: [docs/superpowers/plans/2026-04-01-hide-routes-on-select.md, docs/superpowers/specs/2026-04-01-hide-routes-on-select-design.md, frontend/src/pages/admin/OperatorDashboardPage.tsx]
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

# Execution Log — 2026-04-01 — Ocultar rutas al seleccionar

**Tipo:** feature (enhancement)
**Branch:** `claude/hide-routes-on-select-FqPni`
**Spec:** `docs/superpowers/specs/2026-04-01-hide-routes-on-select-design.md`
**Plan:** `docs/superpowers/plans/2026-04-01-hide-routes-on-select.md`

## Resumen

Al seleccionar (expandir) una ruta en el bottom sheet del Operator Dashboard, las demás rutas se ocultan del mapa. Al deseleccionar, todas vuelven a aparecer.

## Cambios

- **Archivo:** `frontend/src/pages/admin/OperatorDashboardPage.tsx`
- Derivado `visibleRoutes` de `activeRoutes` + `expandedRouteId`
- Reemplazado `activeRoutes` por `visibleRoutes` en RoutePolylineLayer y StopMarkersLayer
- `fitBounds` solo se ejecuta al expandir, no al colapsar
- Eliminada prop `onFocus` de `RouteListItem` (lógica movida a `onToggle`)

## Verificación

| Check | Resultado |
|-------|-----------|
| TypeScript (`tsc --noEmit`) | Sin errores |
| PHP lint (`make lint`) | Sin errores |
| PHPUnit | 6 errors + 5 failures pre-existentes (smoke tests backend, no relacionados) |

## Lecciones

- Cambio UI mínimo (~10 líneas netas) resuelto con derivación de estado existente.
