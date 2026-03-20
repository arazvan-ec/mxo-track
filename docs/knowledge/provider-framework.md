# Provider Framework

**Última actualización:** 2026-03-11
**Estado:** Vigente

## Arquitectura

El framework permite que cada Customer (tenant) elija qué providers usar para routing, optimización, GPS y realtime. Usa el patrón **Transparent Proxy + Provider Factory**.

### Componentes Core

| Componente | Responsabilidad |
|------------|----------------|
| `ServiceType` (enum) | 4 tipos: RoutingEngine, RouteOptimizer, GpsProvider, RealtimePublisher |
| `ProviderFactoryInterface` | Contrato para factories (`create()`, `getProviderType()`, `getServiceType()`) |
| `ProviderFactoryRegistry` | Registro central de factories, auto-discovered via `#[AutoconfigureTag('app.provider_factory')]` |
| `ProviderResolver` | Consulta `CustomerIntegration` para resolver provider del tenant |
| `CachedProviderResolver` | Decorator con caché per-request (in-memory) |
| `TenantContext` | Extrae Customer del SecurityContext |
| `FallbackChain` | Cadena de proveedores por prioridad |
| `ProviderUnavailableException` | Excepción para fallos transitorios |

### Flujo de Resolución

```
Código llama proxy.method() →
  TenantAwareProxy extrae Customer →
    CachedProviderResolver (caché per-request) →
      ProviderResolver consulta CustomerIntegration DB →
        ProviderFactoryRegistry.create(integration) →
          Factory.create(config) → Provider concreto
```

Si no hay CustomerIntegration configurada → usa el provider default (env var).

## Providers Disponibles

| Servicio | Provider | Factory | Config Necesaria | Infra? |
|----------|----------|---------|-----------------|--------|
| Routing | OSRM | OsrmFactory | URL (opcional, env) | Sí |
| Routing | Google Directions | GoogleDirectionsFactory | API key (env fallback) | No |
| Optimizer | VROOM | VroomFactory | URL (opcional, env) | Sí |
| Optimizer | Greedy | GreedyOptimizerFactory | Ninguna | No |
| GPS | Traccar | TraccarFactory | Credenciales (opcional, env) | Sí |
| GPS | Webhook | WebhookGpsFactory | Ninguna | No |
| Realtime | Mercure | MercureFactory | Ninguna (global) | Sí |
| Realtime | HTTP Polling | HttpPollingFactory | Ninguna | No |

## Interfaces (Ports)

| Interfaz | Métodos clave |
|----------|---------------|
| `RoutingEngineInterface` | `calculateRoute(Coordinate $from, Coordinate $to): RouteResult` |
| `RouteOptimizerInterface` | `optimize(array $shipments, array $vehicles, ?Coordinate $depot): OptimizationResult` |
| `GpsPositionProviderInterface` | `getPositions()`, `isAvailable()` |
| `GpsDeviceManagerInterface` | `login()`, `getSessionCookie()`, `getDevices()`, `createDevice()` |
| `RealtimePublisherInterface` | `publish(SseMessage $message): void`, `publishBatch(array $messages): void` |

### GPS Provider Split (ISP/LSP)

`GpsDeviceProviderInterface` was split into two cohesive interfaces:
- `GpsPositionProviderInterface`: Implemented by ALL GPS providers (Traccar, Webhook, Null). Methods: `getPositions()`, `isAvailable()`.
- `GpsDeviceManagerInterface`: Implemented ONLY by Traccar (pull-based device management). Methods: `login()`, `getSessionCookie()`, `getDevices()`, `createDevice()`.

`WebhookGpsProvider` only implements `GpsPositionProviderInterface` — no more stubs.

### TenantAware Proxies

Proxy per interfaz para resolución per-tenant:

- `TenantAwareRoutingEngine` → `RoutingEngineInterface`
- `TenantAwareRouteOptimizer` → `RouteOptimizerInterface`
- `TenantAwareGpsPositionProvider` → `GpsPositionProviderInterface`
- `TenantAwareRealtimePublisher` → `RealtimePublisherInterface`

`GpsDeviceManagerInterface` no tiene proxy — se resuelve directamente a `TraccarGpsProvider` ya que device management es inherentemente Traccar-específico.

Los proxies están registrados como alias en `services.yaml`.

## Enums de Provider

```php
enum RoutingProvider: string {
    case Osrm = 'osrm';
    case GoogleDirections = 'google_directions';
}

enum RouteOptimizerProvider: string {
    case Vroom = 'vroom';
    case Greedy = 'greedy';
}

enum GpsProviderType: string {
    case Traccar = 'traccar';
    case Webhook = 'webhook';
}

enum RealtimeProviderType: string {
    case Mercure = 'mercure';
    case HttpPolling = 'http_polling';
}
```

## Variables de Entorno (Defaults)

```env
DEFAULT_ROUTE_OPTIMIZER=greedy
DEFAULT_ROUTING_ENGINE=google_directions
DEFAULT_GPS_PROVIDER=webhook
DEFAULT_REALTIME_PUBLISHER=mercure
GOOGLE_DIRECTIONS_API_KEY=          # fallback para GoogleDirectionsFactory
```

## CustomerIntegration Entity

Almacena la configuración de providers por tenant:

- `customer` (ManyToOne Customer)
- `serviceType` (ServiceType enum)
- `providerType` (string — valor del enum del provider)
- `config` (JSON — config específica del provider, ej: `{"api_key": "..."}`)
- `enabled` (bool)
- `priority` (int — para fallback chains, menor = más prioritario)

## Cómo Añadir un Nuevo Provider

1. Añadir case al enum correspondiente (ej: `RoutingProvider::GraphHopper`)
2. Crear Config DTO (opcional) con `fromArray(array)`
3. Crear Engine implementando la interfaz del port
4. Crear Factory implementando `ProviderFactoryInterface`
5. Wiring en `services.yaml` si necesita env vars inyectadas
6. Auto-discovered vía `#[AutoconfigureTag('app.provider_factory')]`

## Deuda Técnica

- ~~**GpsDeviceProviderInterface**: `login()` y `getSessionCookie()` son Traccar-específicos. Webhook usa stubs (no-op).~~ **RESUELTO:** Split en `GpsPositionProviderInterface` + `GpsDeviceManagerInterface`.
- ~~**Mercure listeners**: Servicios usaban `HubInterface` directamente en vez de `RealtimePublisherInterface`.~~ **RESUELTO:** Todos los servicios migrados a `RealtimePublisherInterface`. `HubInterface` solo en `MercurePublisher` y `MercureFactory` (infraestructura).
- **Sin encriptación**: API keys en `CustomerIntegration.config` almacenadas en JSON plano.
- **Sin circuit breaker**: No hay mecanismo automático si un provider externo falla.

## Historial

- 2026-03-11: Creación inicial
- 2026-03-11: Eliminado Haversine provider, Google Directions como default
- 2026-03-19: GPS interface split (ISP/LSP fix) + Mercure abstraction cleanup
