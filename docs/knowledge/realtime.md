# Realtime (Mercure SSE)

**Última actualización:** 2026-03-11
**Estado:** Vigente

## Arquitectura

El sistema usa **Mercure** como hub de Server-Sent Events (SSE) para actualizaciones en tiempo real. Hay un provider alternativo **HTTP Polling** para tenants sin Mercure.

## Topics

| Topic | Evento | Publisher | Payload |
|-------|--------|-----------|---------|
| `/vehicles/{publicId}/position` | Nueva posición GPS | MercurePositionListener | `{vehicleId, lat, lng, speed, course, deviceTime}` |
| `/operator/fleet` | Resumen de flota | MercurePositionListener | Agregado de flota |
| `/customers/{id}/routes` | Progreso de ruta (ligero) | MercureRouteProgressListener | `{type, route_public_id, stop_public_id}` |
| `/customers/{id}/shipments` | Estado de envío | MercureRouteProgressListener | `{type, shipment_public_id}` |
| `/routes/{publicId}/view/admin` | Vista completa para admin | RouteSnapshotListener | `MapViewData.toJson()` (incluye métricas) |
| `/routes/{publicId}/view/customer` | Vista completa para cliente | RouteSnapshotListener | `MapViewData.toJson()` |
| `/routes/{publicId}/view/driver` | Vista completa para conductor | RouteSnapshotListener | `MapViewData.toJson()` |
| `/routes/{publicId}/events` | Evento del historial de ruta | RouteEventLogListener | `{type, actor_type, payload, snapshot_metrics, occurred_at}` |

### Tipos de evento en `/customers/{id}/routes`

| `type` | Trigger | Campos extra |
|--------|---------|-------------|
| `stop_delivered` | Conductor marca entrega | `stop_public_id` |
| `stop_exception` | Conductor reporta excepción | `stop_public_id`, `reason` |
| `route_started` | Conductor inicia ruta | — |
| `route_completed` | Ruta finaliza | — |

## Flujo completo: entrega → actualización en tiempo real

```
1. Transportista marca "Entregado" → POST /api/driver/stops/{id}/deliver
2. DeliveryService.deliverStop() → persiste en DB → dispatch(StopDelivered)
3. MercureRouteProgressListener → publica a /customers/{id}/routes
   (evento ligero, trigger para dashboard operador)
4. RouteSnapshotListener:
   a. snapshotManager.updateStopStates($route) → actualiza snapshot en DB
   b. Para cada rol (admin, customer, driver):
      → viewService.buildSingleRouteView($route, $role)
      → hub.publish(Update("/routes/{id}/view/{role}", mapData.toJson()))
5. Frontend: MxoRouteMap.subscribeMercure() recibe el evento
   → MxoRouteMap.update(newData) → re-renderiza mapa
   → Emite evento DOM 'mxo:route-updated' con MapViewData
6. Lista de paradas: escucha 'mxo:route-updated'
   → Actualiza badges de estado, contadores, info de entrega/excepción
```

## Componentes

| Componente | Responsabilidad |
|------------|----------------|
| `MercureJwtFactory` | Genera subscriber tokens (HS256) |
| `MercureTokenController` | Endpoint `/api/mercure-token` → JWT via cookie `mercureAuthorization` |
| `TopicResolver` | Autorización de topics por rol/tenant |
| `MercurePositionListener` | Publica posiciones GPS a Mercure |
| `MercureRouteProgressListener` | Publica eventos de progreso (ligeros) |
| `RouteSnapshotListener` | Actualiza snapshot + publica MapViewData completa por rol |

## Frontend: MxoRouteMap y actualización en tiempo real

| Componente | Archivo | Descripción |
|------------|---------|-------------|
| `MxoRouteMap` | `_map_js.html.twig` | Clase JS compartida. Si `data.mercureTopic` presente, suscribe a Mercure via EventSource |
| `_map.html.twig` | `components/route/` | Wrapper HTML: incluye Leaflet CSS, instancia `MxoRouteMap` al DOMContentLoaded |
| `_stop_list.html.twig` | `components/route/` | Lista de paradas reactiva (Alpine.js). Escucha `mxo:route-updated` para actualizar estados |
| Driver live updater | `driver/routes/show.html.twig` | Listener DOM que actualiza cards de paradas del conductor |

### Vistas que reciben actualizaciones en tiempo real

| Vista | Template | Map ID | Mercure Topic | Lista de paradas reactiva |
|-------|----------|--------|---------------|--------------------------|
| Admin route show | `admin/route/show.html.twig` | `admin-route-map` | `/routes/{id}/view/admin` | Sí (`_stop_list.html.twig`) |
| Customer route show | `customer/route/show.html.twig` | `customer-route-map` | `/routes/{id}/view/customer` | Sí (`_stop_list.html.twig`) |
| Driver route show | `driver/routes/show.html.twig` | `driver-route-map` | `/routes/{id}/view/driver` | Sí (DOM updater inline) |
| Operator dashboard | `operator/dashboard_live.html.twig` | — | `/operator/fleet`, `/vehicles/*/position` | N/A (KPI refresh) |

## Configuración JWT

- Publisher y subscriber usan la misma key (`MERCURE_PUBLISHER_JWT_KEY` = `MERCURE_SUBSCRIBER_JWT_KEY`)
- Algoritmo: HS256
- El token del subscriber se entrega como cookie `mercureAuthorization` (solo el JWT, sin prefijo "Bearer")
- TTL configurable via `MERCURE_SUBSCRIBER_TOKEN_TTL`

## CORS

- Mercure hub debe usar origen específico (`cors_origins http://localhost:8000`), **no `*`**
- `EventSource` usa `withCredentials: true`, requiere CORS explícito

## Configuración en services.yaml

Publisher config en `mercure.yaml` requiere `publish: ['*']` para autorizar publicación a todos los topics.

## HTTP Polling (Alternativa)

Para tenants sin Mercure:
- `HttpPollingPublisher`: Almacena eventos en `RealtimeEvent` entity
- `/api/v1/events` endpoint para polling
- No requiere infraestructura Mercure

## Deuda Técnica

`MercurePositionListener` y `MercureRouteProgressListener` usan `HubInterface` directamente en vez de `RealtimePublisherInterface`. Esto significa que aunque un tenant esté configurado con `HttpPollingPublisher`, los listeners seguirán publicando a Mercure. Refactorizar antes de tener tenants con HTTP Polling.

## Historial

- 2026-03-11: Creación inicial
- 2026-03-13: Documentar flujo completo de actualización en tiempo real, topics por rol, RouteSnapshotListener, componentes frontend reactivos
- 2026-03-13: Añadir topic `/routes/{publicId}/events` para historial de eventos en tiempo real
