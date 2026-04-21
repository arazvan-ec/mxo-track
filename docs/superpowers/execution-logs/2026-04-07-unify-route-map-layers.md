---
type: refactor
tags: [map, route]
files_touched: [docs/superpowers/plans/2026-04-07-unify-route-map-layers.md, docs/superpowers/specs/2026-04-07-unify-route-map-layers-design.md]
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

# Execution Log — 2026-04-07 — Unify Route Map Layers

**Type:** refactor (unificación)
**Branch:** `claude/unify-map-route-filter-b9Flc`
**Spec:** `docs/superpowers/specs/2026-04-07-unify-route-map-layers-design.md`
**Plan:** `docs/superpowers/plans/2026-04-07-unify-route-map-layers.md`

## Brainstorming

- **Problema:** FleetMapPage y OperatorDashboardPage ensamblaban las mismas capas de mapa independientemente. Al añadir el toggle de flechas de dirección a FleetMap, se olvidó en OperatorDashboard.
- **Alternatives:** (A) Hook compartido `useRouteMapLayers`, (B) Componente `<RouteMapLayers>`, (C) Sincronización manual
- **Chosen:** B — componente compartido. Más natural en React, imposible olvidar features.
- **Complexity estimate:** Media — 4 archivos, ~104 líneas nuevas, ~130 líneas eliminadas

## Planning

- 4 tareas: crear componente, migrar FleetMap, migrar OperatorDashboard, verificar
- Archivos afectados: 1 nuevo, 3 modificados

## Implementation

- Sin blockers ni desviaciones
- `RouteMapLayers` centraliza: RoutePolylineLayer + StopMarkersLayer + StopPopup + botón toggle showArrows
- Soporta dos modos de selección de stops: `selectedRouteId+selectedStopSequence` (FleetMap) y `selection` object (OperatorDashboard)
- FleetMap wrapper: -55 líneas, ahora delega a RouteMapLayers + conserva VehicleMarker + VehicleTrailLayer
- OperatorDashboardPage: -57 líneas, reemplaza ensamblaje manual por RouteMapLayers + conserva VehicleLayer WebGL
- VehicleTrailLayer arrows: dejadas como default `true` (independientes del toggle de rutas)

## Verification

| Check | Resultado |
|-------|-----------|
| TypeScript (`tsc --noEmit`) | ✅ Sin errores |
| Imports huérfanos | ✅ Eliminados (StopMarkersLayer, RoutePolylineLayer, StopPopup de ambas páginas) |

## Retrospective

- Estimación precisa — 4 tareas ejecutadas sin fricción
- **Lección clave:** Cuando dos páginas comparten capas de mapa, centralizar en un componente compartido. Si no, cada feature nueva se olvida en una de las dos
- El primer intento (commit `7d7ef9d`) solo copió el toggle — resolvía el síntoma pero no la causa estructural
- **Patrón aplicable:** Cualquier otro mapa que use RoutePolylineLayer + StopMarkersLayer debería considerar usar RouteMapLayers
