# Plan: Fase 3 — Test Routing + Operator Dashboard

**Spec:** `docs/superpowers/specs/2026-03-18-map-domain-react-migration-design.md`
**Prerequisito:** Fase 1 completada
**Objetivo:** Migrar test routing comparison view y operator live dashboard.

## Tareas

### 3.1 Backend: Comparison API endpoint
- [ ] Crear `ComparisonMapDataController` con `GET /api/map/comparison`
- [ ] Recibe route IDs, retorna original + optimized con metrics
- [ ] Commit + push

### 3.2 ComparisonLayer component
- [ ] Crear `layers/ComparisonLayer.tsx` (dos polylines: original dashed red, optimized solid)
- [ ] Sidebar: métricas globales (distance before/after, savings %, route count)
- [ ] Commit + push

### 3.3 Test Routing Page
- [ ] Crear `pages/admin/TestRoutingPage.tsx`
- [ ] MapCanvas + ComparisonLayer + StopMarkersLayer
- [ ] Side-by-side stop order comparison
- [ ] Commit + push

### 3.4 Operator Live Dashboard
- [ ] Crear `pages/admin/OperatorDashboardPage.tsx`
- [ ] Variante del FleetMap con KPIs operativos (active routes, deliveries/h, exceptions)
- [ ] Reutilizar MapCanvas + VehicleLayer
- [ ] Real-time table de rutas activas con progreso
- [ ] Commit + push

### 3.5 Router
- [ ] Añadir rutas al router
- [ ] Verificación TypeScript
- [ ] Commit + push
