# Execution Log — 2026-04-01 — Stop Details Expansion

**Tipo:** feature (enhancement)
**Branch:** `claude/stop-details-expansion-IVLxp`
**Spec:** `docs/superpowers/specs/2026-04-01-stop-details-expansion-design.md`
**Plan:** `docs/superpowers/plans/2026-04-01-stop-details-expansion.md`

## Resumen

Implementada expansion inline de paradas en el Operations Dashboard bottom sheet. Al pulsar una parada en la lista de ruta, se expande mostrando detalles adicionales (telefono, hora de entrega, excepcion) y botones de accion (Localizar, Copiar, Llamar, Ver envio).

## Cambios realizados

### Backend
- `RouteSnapshotManager::buildStopStates()` — agregado `recipientPhone` al array de estado del snapshot para que `StopMapView::toArray()` lo incluya en la respuesta API.

### Frontend
- `FleetStop` type — extendido con campos opcionales: `recipientName`, `recipientPhone`, `deliveredAt`, `exceptionCode`, `exceptionNotes`
- `RouteListItem` — agregado estado `expandedStopKey` para controlar que parada esta expandida
- `StopItem` — transformado de boton simple a componente expandible:
  - Tap toggle expansion (antes volaba al mapa)
  - Area expandida muestra: telefono, hora entrega, excepcion + notas
  - Botones: Localizar (vuela al mapa), Copiar direccion, Llamar, Ver envio

## Verificacion

- TypeScript: sin errores
- Build: OK (7.81s)
- Backend lint: sin errores de sintaxis
- Tests: pendiente resultado

## Decisiones

- Se mantuvo `stop.recipient` como fallback junto a `stop.recipientName` por backward compat
- No se incluyeron ETAs (requiere cambio en `RouteMapOptions` — scope separado)
- No se incluyeron delivery windows ni notes — pueden agregarse en iteracion futura

## Lecciones

- El tipo `FleetStop` no tenia campos que el backend ya enviaba (recipientPhone, deliveredAt, etc.) — la API era mas rica que el frontend consumia
