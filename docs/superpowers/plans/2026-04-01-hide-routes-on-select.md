# Plan — Ocultar rutas al seleccionar una

**Spec:** `docs/superpowers/specs/2026-04-01-hide-routes-on-select-design.md`
**Archivo afectado:** `frontend/src/pages/admin/OperatorDashboardPage.tsx`

## Phase 1 (v0) — Implementación directa

### Tarea 1: Derivar visibleRoutes y usarlas en el mapa

1. Añadir `visibleRoutes` derivado de `activeRoutes` + `expandedRouteId`
2. Reemplazar `activeRoutes` por `visibleRoutes` en RoutePolylineLayer y StopMarkersLayer
3. Condicionar `onFocus` (fitBounds) a solo ejecutarse al expandir

**Verificación:** Build sin errores, comportamiento visual correcto.

## Phase 2 — No aplica

Cambio mínimo (~5 líneas), no requiere refactor posterior.
