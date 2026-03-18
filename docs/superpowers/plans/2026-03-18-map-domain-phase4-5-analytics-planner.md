# Plan: Fase 4+5 — Analytics Maps + Route Planner

**Spec:** `docs/superpowers/specs/2026-03-18-map-domain-react-migration-design.md`
**Prerequisito:** Fases 0-3 completadas

## Fase 4: Exception Map + Route Analysis

### 4.1 Exception Map
- [ ] Backend: `GET /api/map/exceptions` (exception stops with coords + metadata)
- [ ] `layers/ExceptionLayer.tsx` (heatmap o cluster markers de excepciones)
- [ ] `pages/admin/ExceptionMapPage.tsx` con filtros por fecha/tipo
- [ ] Commit + push

### 4.2 Route Analysis
- [ ] Backend: `GET /api/map/route/{publicId}/analysis` (performance metrics overlay)
- [ ] `pages/admin/RouteAnalysisPage.tsx` — route polyline + performance overlay
- [ ] Sidebar: métricas detalladas (planned vs actual, timing breakdown)
- [ ] Commit + push

## Fase 5: Route Planner (requiere brainstorming separado)

El Route Planner es la vista más compleja del sistema:
- Clustering interactivo de shipments (k-means++ visual)
- Drag-drop de shipments entre clusters
- Preview de rutas (multi-route visualization)
- Selección de vehículos + drivers con scoring
- Confirmación + creación de rutas

**Acción:** Ejecutar brainstorming skill dedicado antes de implementar.

### 5.1 Tareas preliminares
- [ ] Brainstorming: diseño de UI interactiva del planner
- [ ] Spec: interacciones drag-drop, estados, flujo de confirmación
- [ ] Plan detallado con pasos atómicos
- [ ] Implementación (estimación: la fase más larga)
