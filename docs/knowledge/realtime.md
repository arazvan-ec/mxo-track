# Realtime (Mercure SSE)

**Última actualización:** 2026-03-11
**Estado:** Vigente

## Arquitectura

El sistema usa **Mercure** como hub de Server-Sent Events (SSE) para actualizaciones en tiempo real. Hay un provider alternativo **HTTP Polling** para tenants sin Mercure.

## Topics

| Topic | Evento | Publisher |
|-------|--------|-----------|
| `/vehicles/{public_id}/position` | Nueva posición GPS | MercurePositionListener |
| `/operator/fleet` | Resumen de flota | MercurePositionListener |
| `/customers/{id}/routes` | Progreso de ruta | MercureRouteProgressListener |
| `/customers/{id}/shipments` | Estado de envío | MercureRouteProgressListener |

## Componentes

| Componente | Responsabilidad |
|------------|----------------|
| `MercureJwtFactory` | Genera subscriber tokens (HS256) |
| `MercureTokenController` | Endpoint `/api/mercure-token` → JWT via cookie `mercureAuthorization` |
| `TopicResolver` | Autorización de topics por rol/tenant |
| `MercurePositionListener` | Publica posiciones GPS a Mercure |
| `MercureRouteProgressListener` | Publica progreso de rutas |

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
