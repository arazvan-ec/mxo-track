# Spec: Stop Details Expansion in Bottom Sheet

**Fecha:** 2026-04-01
**Tipo:** Feature (enhancement)
**Branch:** `claude/stop-details-expansion-IVLxp`

## Problema

En el Operations Dashboard, al expandir una ruta en el bottom sheet, las paradas muestran solo: icono de estado, secuencia, dirección, destinatario y estado. No hay forma de ver detalles sin seleccionar la parada en el mapa (lo que pierde contexto de la lista).

## Diseño: Opcion C — Expansion inline + boton "Localizar"

Al pulsar una parada en la lista del bottom sheet:
1. Se expande in situ mostrando detalles adicionales
2. Un segundo tap colapsa la parada
3. Solo una parada expandida a la vez

### Datos mostrados en expansion

| Campo | Cuando se muestra |
|-------|-------------------|
| Telefono destinatario | Si existe |
| ETA (hora) | Si PENDING y hay ETA |
| Hora de entrega | Si DELIVERED |
| Codigo de excepcion + notas | Si EXCEPTION |

### Botones de accion

| Boton | Siempre visible | Accion |
|-------|----------------|--------|
| Localizar en mapa | Si | Fly to marker en mapa |
| Copiar direccion | Si | Clipboard |
| Ver envio | Si tiene shipmentPublicId | Link a `/admin/shipments/{id}` |
| Llamar | Si tiene telefono | `tel:` link |

### Cambios backend

`RouteSnapshotManager::buildStopStates()` no incluye `recipientPhone`. Hay que agregarlo para que `StopMapView::toArray()` lo retorne.

### Cambios frontend

1. Extender tipo `FleetStop` con campos opcionales: recipientPhone, deliveredAt, etaTime, exceptionCode, exceptionNotes
2. `StopItem` se convierte en componente expandible con estado `expandedStopKey` en `RouteListItem`
3. El tap actual (que volaba al mapa) se reemplaza por toggle de expansion
4. El vuelo al mapa se mueve a un boton "Localizar" dentro de la expansion

## Existing Functionality Inventory

| Elemento | Decision | Justificacion |
|----------|----------|---------------|
| `StopItem` component | Transform | Agregar estado expandido |
| `RouteListItem` component | Transform | Manejar `expandedStopKey` |
| `handleStopClick` en OperatorDashboardPage | Transform | Pasar como prop a boton "Localizar" |
| `StopActionPanel` | Include (sin cambios) | Sigue funcionando para seleccion desde mapa |
| `FleetStop` type | Transform | Agregar campos opcionales |
| `buildStopStates()` backend | Transform | Agregar recipientPhone |

## Omission Decisions

| Elemento | Decision | Justificacion |
|----------|----------|---------------|
| deliveryWindowStart/End | Omit | No prioritario, se puede agregar despues |
| notes/aiNotes | Omit | No prioritario |
| ETAs (backend) | Omit | Requiere `includeEtas` en RouteMapOptions, scope separado |
