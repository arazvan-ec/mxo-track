# Plan: Fleet Map React Migration

**Spec:** `docs/superpowers/specs/2026-03-18-fleet-map-react-migration-design.md`
**Branch:** `claude/map-domain-reactive-routes-iXBRx`

## Etapas

### Etapa 1: Hooks de datos
- [ ] 1.1 Crear `useFleetKpi` hook (fetch `/api/fleet/summary`)
- [ ] 1.2 Crear `useMercureRouteUpdates` hook (SSE route updates)
- [ ] 1.3 Crear `useVehicleTrail` hook (fetch trail positions)
- [ ] 1.4 Integrar `useMercureRouteUpdates` en `useFleetMapData`

### Etapa 2: Componentes del sidebar
- [ ] 2.1 Crear `KpiPills` component
- [ ] 2.2 Crear `VehicleList` component (extraer de FleetMapPage)
- [ ] 2.3 Crear `RouteList` component
- [ ] 2.4 Crear `RouteProgressBar` component
- [ ] 2.5 Crear `FleetSidebar` que compone todo

### Etapa 3: Componentes del mapa
- [ ] 3.1 Crear `VehiclePopup` component
- [ ] 3.2 Mejorar `VehicleMarker` con popup on click
- [ ] 3.3 Crear `RouteSegments` component (polylines por status)
- [ ] 3.4 Crear `VehicleTrail` component
- [ ] 3.5 Crear `HeaderBar` component (clock, SSE, links)

### Etapa 4: Integración en FleetMapPage
- [ ] 4.1 Reescribir `FleetMapPage` con state management completo
- [ ] 4.2 Fly-to on vehicle/route selection
- [ ] 4.3 Conditional rendering de stops/trail según selección

### Etapa 5: Estilos y polish
- [ ] 5.1 Dark theme matching (Twig parity)
- [ ] 5.2 Verificación visual completa
