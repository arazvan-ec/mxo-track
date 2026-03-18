# Plan: Fase 2 — Customer + Driver Route Views

**Spec:** `docs/superpowers/specs/2026-03-18-map-domain-react-migration-design.md`
**Prerequisito:** Fase 1 completada
**Objetivo:** Migrar route detail views para customer y driver portals.

## Tareas

### 2.1 Backend: role-based options en RouteMapDataController
- [ ] Añadir lógica de rol: ROLE_CUSTOMER → sin metrics/timing/comparison
- [ ] ROLE_DRIVER → con ETAs prominentes, sin metrics admin
- [ ] Verificar tenant scoping (customer solo ve sus rutas)
- [ ] Commit + push

### 2.2 Customer Route Detail Page
- [ ] Crear `pages/customer/CustomerRouteDetailPage.tsx`
- [ ] Reutilizar MapCanvas + RoutePolylineLayer + StopMarkersLayer
- [ ] Sidebar simplificado: StopListPanel (sin metrics, con ETAs)
- [ ] Commit + push

### 2.3 Driver Route Page
- [ ] Crear `pages/driver/DriverRoutePage.tsx`
- [ ] Sidebar: StopListPanel con ETAs prominentes + estado de entrega
- [ ] Vehicle position auto-tracking (pan to vehicle)
- [ ] Commit + push

### 2.4 Router + navegación
- [ ] Añadir rutas `/app/customer/routes/:publicId` y `/app/driver/routes/:publicId`
- [ ] Verificación TypeScript
- [ ] Commit + push
