# Plan: Colorize Map Routes

**Goal:** Make multi-route maps visually distinguishable with per-route stop marker colors and directional arrows.
**Spec:** `docs/superpowers/specs/2026-03-20-colorize-map-routes-design.md`
**Architecture:** React frontend only (MapLibre GL + react-map-gl)
**Approach:** Prop threading (Approach A)

## File Structure

| Archivo | Accion |
|---------|--------|
| `frontend/src/components/maps/shared/StopMarker.tsx` | Modificar — añadir `routeColor` prop |
| `frontend/src/components/maps/layers/StopMarkersLayer.tsx` | Modificar — añadir `routeColor` prop, pasar a StopMarker |
| `frontend/src/components/maps/layers/RoutePolylineLayer.tsx` | Modificar — añadir symbol layer con flechas direccionales |
| `frontend/src/pages/admin/TestRoutingPage.tsx` | Modificar — pasar routeColor a StopMarkersLayer |
| `frontend/src/pages/admin/RoutePlannerPage.tsx` | Modificar — pasar routeColor a StopMarker en preview |
| `frontend/src/components/maps/FleetMap.tsx` | Modificar — pasar routeColor a StopMarker |
| `frontend/src/pages/admin/RouteDetailPage.tsx` | Modificar — pasar routeColor a StopMarkersLayer |
| `frontend/src/pages/customer/CustomerRouteDetailPage.tsx` | Modificar — pasar routeColor a StopMarkersLayer |
| `frontend/src/pages/driver/DriverRoutePage.tsx` | Modificar — pasar routeColor a StopMarkersLayer |
| `frontend/src/pages/admin/RouteAnalysisPage.tsx` | Modificar — pasar routeColor a StopMarkersLayer |

## Tasks

### Task 1: StopMarker — añadir routeColor prop

- [ ] Archivo: `frontend/src/components/maps/shared/StopMarker.tsx`
- Añadir prop `routeColor?: string` a la interface Props
- Lógica: si status es PENDING y routeColor existe → usar routeColor. Sino → status color como antes.
- Commit: `feat: add routeColor prop to StopMarker`

### Task 2: StopMarkersLayer — pasar routeColor

- [ ] Archivo: `frontend/src/components/maps/layers/StopMarkersLayer.tsx`
- Añadir prop `routeColor?: string` a la interface Props
- Pasar `routeColor` a cada `<StopMarker>`
- Commit: `feat: add routeColor prop to StopMarkersLayer`

### Task 3: RoutePolylineLayer — flechas direccionales

- [ ] Archivo: `frontend/src/components/maps/layers/RoutePolylineLayer.tsx`
- Añadir prop `showArrows?: boolean` (default: `!dashed`)
- Crear un `<Layer type="symbol">` adicional dentro del Source con:
  - `symbol-placement: 'line'`
  - `symbol-spacing: 100`
  - `text-field: '▶'`
  - `text-color` = route color
  - `text-halo-color: rgba(0,0,0,0.7)` para contraste
- Solo renderizar si `showArrows` es true
- Commit: `feat: add directional arrows to RoutePolylineLayer`

### Task 4: Actualizar páginas multi-ruta (TestRoutingPage, RoutePlannerPage)

- [ ] Archivo: `frontend/src/pages/admin/TestRoutingPage.tsx`
  - En el map de `routesData` para stop markers, calcular `routeColor = ROUTE_COLORS[idx % ROUTE_COLORS.length]` y pasarlo a StopMarkersLayer
- [ ] Archivo: `frontend/src/pages/admin/RoutePlannerPage.tsx`
  - En step ≥3 preview stops, calcular routeColor del índice y pasarlo a cada StopMarker
- Commit: `feat: pass routeColor to stop markers in multi-route views`

### Task 5: Actualizar páginas single-route y FleetMap

- [ ] Archivo: `frontend/src/components/maps/FleetMap.tsx` — pasar `activeStops.color` a StopMarker
- [ ] Archivo: `frontend/src/pages/admin/RouteDetailPage.tsx` — pasar `route.color` a StopMarkersLayer
- [ ] Archivo: `frontend/src/pages/customer/CustomerRouteDetailPage.tsx` — pasar `route.color`
- [ ] Archivo: `frontend/src/pages/driver/DriverRoutePage.tsx` — pasar `route.color`
- [ ] Archivo: `frontend/src/pages/admin/RouteAnalysisPage.tsx` — pasar `'#3b82f6'` (blue)
- Commit: `feat: pass routeColor to stop markers in single-route views`

### Task 6: Verificación

- [ ] TypeScript compile: `npx tsc -b --noEmit`
- [ ] ESLint: `npx eslint` en archivos modificados
- [ ] Build: `npx vite build`
- Commit final si hay fixes necesarios
