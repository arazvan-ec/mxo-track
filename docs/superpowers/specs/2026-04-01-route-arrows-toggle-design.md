# Spec: Toggle de flechas de dirección en el mapa

**Fecha:** 2026-04-01
**Tipo:** Enhancement (UI)
**Contexto acotado:** Pragmático (React SPA)

## Problema

El mapa de flota muestra flechas de dirección en las polylines de ruta y trail de vehículos, pero no hay forma de ocultarlas. El usuario necesita un toggle para activar/desactivar las flechas según preferencia.

## Diseño

### Enfoque: Toggle en FleetMapPage con prop passthrough

1. **FleetMapPage** — nuevo estado `showArrows` (default: `true`), botón overlay sobre el mapa
2. **FleetMap** — nueva prop `showArrows`, la pasa a `RoutePolylineLayer` y `VehicleTrailLayer`
3. **RoutePolylineLayer** y **VehicleTrailLayer** — ya soportan `showArrows` prop, no necesitan cambios

### Botón toggle

- Posición: esquina superior izquierda del mapa (`absolute top-4 left-4`)
- Estilo: consistente con "Fit all" de TestRoutingPage (`bg-slate-800/90 text-slate-200 rounded-lg border border-slate-600`)
- Estado visual: opacidad reducida cuando arrows están desactivadas
- Texto/icono: "▶▶" (usa los mismos caracteres de flecha que el layer)

## Existing Functionality Inventory

| Elemento | Decisión | Justificación |
|----------|----------|---------------|
| `directionArrowsConfig()` | Sin cambios | Config compartida, no afectada |
| `RoutePolylineLayer.showArrows` | Incluir (usar) | Ya soporta la prop |
| `VehicleTrailLayer.showArrows` | Incluir (usar) | Ya soporta la prop |
| `FleetMap` component | Transformar | Añadir prop `showArrows` |
| `FleetMapPage` | Transformar | Añadir estado + botón |
| `MapCanvas` | Sin cambios | No afectado |

## Omission Decisions

| Elemento | Decisión | Justificación |
|----------|----------|---------------|
| Otras páginas (TestRouting, RouteDetail, etc.) | Omitir | El usuario pidió toggle en el mapa de flota. Otras páginas pueden adoptarlo después |
| Persistencia en localStorage | Omitir | YAGNI para v0, se puede añadir si el usuario lo pide |
