# Plan: Flechas de dirección transversales en todos los mapas

**Spec:** `docs/superpowers/specs/2026-04-01-map-direction-arrows-design.md`
**Branch:** `claude/add-map-direction-arrows-bFPti`

## Goal

Centralizar la configuración de flechas de dirección en un helper compartido y añadirlas al `VehicleTrailLayer`.

## Architecture

- **Helper:** `frontend/src/components/maps/shared/directionArrows.ts`
- **Consumers:** `RoutePolylineLayer.tsx`, `VehicleTrailLayer.tsx`

## File Structure

```
frontend/src/components/maps/
├── shared/
│   ├── directionArrows.ts   ← NEW: config helper
│   ├── polyline.ts
│   └── colors.ts
├── layers/
│   ├── RoutePolylineLayer.tsx  ← MODIFY: use shared config
│   └── VehicleTrailLayer.tsx   ← MODIFY: add arrows with shared config
```

## Tasks

### Task 1: Crear helper `directionArrows.ts`

- [ ] Crear `frontend/src/components/maps/shared/directionArrows.ts`

```typescript
/**
 * Shared MapLibre symbol layer configuration for direction arrows along polylines.
 */
export function directionArrowsConfig(color: string) {
  return {
    layout: {
      'symbol-placement': 'line' as const,
      'symbol-spacing': 100,
      'text-field': '▶',
      'text-size': 12,
      'text-rotation-alignment': 'map' as const,
      'text-allow-overlap': true,
      'text-ignore-placement': true,
      'text-keep-upright': false,
    },
    paint: {
      'text-color': color,
      'text-halo-color': 'rgba(0,0,0,0.7)',
      'text-halo-width': 1,
    },
  };
}
```

- [ ] Commit: `feat: add shared direction arrows config helper`

### Task 2: Refactorizar `RoutePolylineLayer` para usar helper

- [ ] Importar `directionArrowsConfig` desde `../shared/directionArrows`
- [ ] Reemplazar configuración inline del symbol layer por spread de `directionArrowsConfig(color)`
- [ ] Verificar que no hay cambios funcionales (mismas props)

**Antes:**
```tsx
<Layer
  id={`route-arrows-${id}`}
  type="symbol"
  layout={{
    'symbol-placement': 'line',
    'symbol-spacing': 100,
    'text-field': '▶',
    // ... 5 more props
  }}
  paint={{
    'text-color': color,
    'text-halo-color': 'rgba(0,0,0,0.7)',
    'text-halo-width': 1,
  }}
/>
```

**Después:**
```tsx
<Layer
  id={`route-arrows-${id}`}
  type="symbol"
  {...directionArrowsConfig(color)}
/>
```

- [ ] Commit: `refactor: use shared direction arrows config in RoutePolylineLayer`

### Task 3: Añadir flechas a `VehicleTrailLayer`

- [ ] Añadir prop `showArrows?: boolean` con default `true`
- [ ] Importar `directionArrowsConfig`
- [ ] Añadir `<Layer>` de tipo symbol dentro del `<Source>` existente, condicionado a `showArrows`
- [ ] Usar color del trail (`#3b82f6`) para las flechas

**Código resultante:**
```tsx
export function VehicleTrailLayer({ coordinates, showArrows = true }: Props) {
  // ... existing geojson creation ...
  return (
    <Source id="vehicle-trail" type="geojson" data={geojson}>
      <Layer id="vehicle-trail-line" type="line" ... />
      {showArrows && (
        <Layer
          id="vehicle-trail-arrows"
          type="symbol"
          {...directionArrowsConfig('#3b82f6')}
        />
      )}
    </Source>
  );
}
```

- [ ] Commit: `feat: add direction arrows to VehicleTrailLayer`

### Task 4: Verificación

- [ ] `make lint` pasa
- [ ] Verificar que no hay errores de TypeScript: `cd frontend && npx tsc --noEmit`
