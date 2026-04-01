# Spec: Flechas de dirección transversales en todos los mapas

**Fecha:** 2026-04-01
**Tipo:** Enhancement
**Branch:** `claude/add-map-direction-arrows-bFPti`

## Problema

`RoutePolylineLayer` tiene flechas de dirección (▶) pero `VehicleTrailLayer` no. Las flechas deberían estar en todos los mapas que muestran polilíneas.

## Diseño aprobado: Enfoque C — Constante compartida de configuración

Extraer la configuración del symbol layer de flechas a un helper compartido en `shared/directionArrows.ts`. Cada componente de polilínea crea su propio `<Layer>` dentro de su `<Source>`, pero usa la configuración centralizada.

### API del helper

```typescript
// shared/directionArrows.ts
export function directionArrowsConfig(color: string): { layout: object; paint: object }
```

Retorna los objetos `layout` y `paint` para un MapLibre symbol layer con:
- `symbol-placement: 'line'`
- `symbol-spacing: 100`
- `text-field: '▶'`
- `text-size: 12`
- `text-rotation-alignment: 'map'`
- `text-allow-overlap: true`
- `text-ignore-placement: true`
- `text-keep-upright: false`
- `text-color`: parametrizado por `color`
- `text-halo-color: 'rgba(0,0,0,0.7)'`
- `text-halo-width: 1`

### Cambios por componente

**`RoutePolylineLayer.tsx`** — Reemplazar configuración inline por `directionArrowsConfig(color)`.

**`VehicleTrailLayer.tsx`** — Añadir prop `showArrows?: boolean` (default `true`). Añadir `<Layer>` de flechas usando `directionArrowsConfig('#3b82f6')`.

## Existing Functionality Inventory

| Elemento | Decisión | Justificación |
|----------|----------|---------------|
| `RoutePolylineLayer` flechas inline | **Transform** | Extraer config a helper compartido |
| `VehicleTrailLayer` sin flechas | **Transform** | Añadir flechas con config compartida |
| `shared/polyline.ts` | **Include** | Mismo directorio para el nuevo helper |
| `shared/colors.ts` | **Include** | Mismo directorio, sin cambios |

## Omission Decisions

| Elemento | Decisión | Justificación |
|----------|----------|---------------|
| Marker layers (Stop, Vehicle, etc.) | Omit | No renderizan polilíneas |
| MapCanvas | Omit | Componente base, no renderiza rutas |
