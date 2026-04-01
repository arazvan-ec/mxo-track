# Plan: Toggle de flechas de dirección en el mapa

**Spec:** `docs/superpowers/specs/2026-04-01-route-arrows-toggle-design.md`
**Archivos afectados:** 2 (FleetMap.tsx, FleetMapPage.tsx)
**Complejidad:** Baja (~20 líneas netas)

## Phase 1 (v0) — Implementación

### Tarea 1: Añadir prop `showArrows` a FleetMap

**Archivo:** `frontend/src/components/maps/FleetMap.tsx`
- Añadir prop `showArrows?: boolean` a la interfaz Props
- Pasar `showArrows` a `RoutePolylineLayer` y `VehicleTrailLayer`

### Tarea 2: Añadir estado y botón toggle en FleetMapPage

**Archivo:** `frontend/src/pages/admin/FleetMapPage.tsx`
- Añadir estado `const [showArrows, setShowArrows] = useState(true)`
- Pasar `showArrows` a `<FleetMap>`
- Añadir botón overlay sobre el mapa (dentro de `div.flex-1.relative`)

### Tarea 3: Verificar

- `npx tsc --noEmit` sin errores
- Verificación visual (no hay tests unitarios para componentes de mapa)
