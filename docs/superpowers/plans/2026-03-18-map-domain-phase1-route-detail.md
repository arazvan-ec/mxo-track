# Plan: Fase 1 — Route Detail Page (Admin)

**Spec:** `docs/superpowers/specs/2026-03-18-map-domain-react-migration-design.md`
**Branch:** `claude/map-domain-reactive-routes-iXBRx`
**Prerequisito:** Fase 0 completada
**Objetivo:** Migrar la vista admin route detail (`/admin/routes/{id}/show`) a React.

## Tareas

### 1.1 Backend: API endpoint para route map data
- [ ] Crear `RouteMapDataController` con `GET /api/map/route/{publicId}`
- [ ] Reutilizar `RouteViewService::buildSingleRouteView()` existente
- [ ] Serializar MapViewData a JSON con options por rol (admin = full metrics)
- [ ] Commit + push

### 1.2 Hook: useRouteMapData
- [ ] Crear `hooks/useRouteMapData.ts` — fetch + React Query
- [ ] Integrar `useMapSubscription` para SSE de la ruta + vehículo
- [ ] Commit + push

### 1.3 Route Detail Page
- [ ] Crear `pages/admin/RouteDetailPage.tsx`
- [ ] Composición: MapCanvas + RoutePolylineLayer + StopMarkersLayer + VehicleLayer
- [ ] Sidebar: StopListPanel + RouteMetricsPanel
- [ ] Fly-to on stop click
- [ ] Commit + push

### 1.4 Route event timeline
- [ ] Crear `components/panels/RouteTimelinePanel.tsx` (lista de eventos de la ruta)
- [ ] Integrar en RouteDetailPage sidebar
- [ ] SSE: nuevos eventos aparecen en tiempo real
- [ ] Commit + push

### 1.5 Router + navegación
- [ ] Añadir ruta `/app/admin/routes/:publicId` al router
- [ ] Verificación TypeScript + visual
- [ ] Commit + push
