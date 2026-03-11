# User-Configurable Providers — Design Spec

**Date:** 2026-03-11
**Status:** Draft
**Scope:** Per-tenant configurable service providers with extensible architecture

## Goal

Allow each Customer (tenant) to configure which external service providers to use for routing, route optimization, GPS tracking, and real-time updates. The system must support fallback chains, be easily extensible with new providers, and maintain full backward compatibility.

## Architecture: Provider Factory + Transparent Proxy

### Core Principle

Existing domain services (`RouteBuilder`, `RouteOptimizationService`, etc.) remain untouched. They continue to depend on their existing port interfaces (`RouteOptimizerInterface`, `RoutingEngineInterface`, etc.). A transparent proxy layer resolves the correct provider implementation at runtime based on the current tenant's configuration.

### Key Components

```
┌─────────────────────────────────────────────────────┐
│  Domain Services (unchanged)                         │
│  RouteBuilder, RouteOptimizationService, etc.        │
│  Depend on: RouteOptimizerInterface,                 │
│             RoutingEngineInterface, etc.              │
└──────────────────────┬──────────────────────────────┘
                       │ (DI alias)
┌──────────────────────▼──────────────────────────────┐
│  Transparent Proxies (NEW)                           │
│  TenantAwareRouteOptimizer                           │
│  TenantAwareRoutingEngine                            │
│  TenantAwareGpsProvider                              │
│  TenantAwareRealtimePublisher                        │
│  Implement same interfaces, resolve provider at call │
└──────────────────────┬──────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────┐
│  ProviderResolver (NEW)                              │
│  - Reads CustomerIntegration from DB (cached)        │
│  - Falls back to global defaults if no config        │
│  - Supports fallback chains via priority             │
└──────────────────────┬──────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────┐
│  ProviderFactoryRegistry (NEW)                       │
│  - Maps provider type string → ProviderFactory       │
│  - Autodiscovery via Symfony tagged services          │
│  - Creates configured provider instances              │
└──────────────────────┬──────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────┐
│  Concrete Providers                                  │
│  VroomRouteOptimizer, OsrmRoutingEngine (existing)   │
│  GreedyOptimizer, HaversineEngine, etc. (new)        │
└─────────────────────────────────────────────────────┘
```

## Domain Model

### ServiceType Enum

```php
enum ServiceType: string {
    case RouteOptimizer = 'route_optimizer';
    case RoutingEngine = 'routing_engine';
    case GpsProvider = 'gps_provider';
    case RealtimePublisher = 'realtime_publisher';
}
```

### Provider Enums (one per service, type-safe)

```php
// Only v1 providers included. Add new cases when implementing new providers.
enum RouteOptimizerProvider: string {
    case Vroom = 'vroom';
    case Greedy = 'greedy';
}

enum RoutingProvider: string {
    case Osrm = 'osrm';
    case Haversine = 'haversine';
    case GoogleDirections = 'google_directions';
}

enum GpsProvider: string {
    case Traccar = 'traccar';
    case Webhook = 'webhook';
}

enum RealtimeProvider: string {
    case Mercure = 'mercure';
    case HttpPolling = 'http_polling';
}
```

### CustomerIntegration Entity

```php
#[ORM\Entity(repositoryClass: CustomerIntegrationRepository::class)]
#[ORM\Table(name: 'customer_integration')]
#[ORM\UniqueConstraint(columns: ['customer_id', 'service_type', 'priority'])]
#[ORM\HasLifecycleCallbacks]
class CustomerIntegration implements CustomerScopedEntityInterface
{
    use PublicIdTrait;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Customer $customer;

    #[ORM\Column(enumType: ServiceType::class)]
    private ServiceType $serviceType;

    #[ORM\Column(length: 50)]
    private string $providerType;  // Value from per-service enum

    #[ORM\Column(type: 'json')]
    private array $config = [];  // Provider-specific config (API keys, URLs, options)

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column(type: 'smallint')]
    private int $priority = 0;  // 0 = primary, 1+ = fallbacks

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;
}
```

### Config DTOs (per provider, type-safe validation)

Each provider defines a config DTO with Symfony Validator constraints:

```php
class GoogleDirectionsConfig {
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $apiKey,
        public readonly string $region = 'es',
        public readonly bool $avoidTolls = false,
        public readonly bool $avoidHighways = false,
    ) {}

    public static function fromArray(array $data): self { /* ... */ }
}

class VroomConfig {
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Url]
        public readonly string $url,
        public readonly int $timeoutSeconds = 30,
    ) {}

    public static function fromArray(array $data): self { /* ... */ }
}
```

## Provider Resolution

### ProviderResolver

```php
interface ProviderResolverInterface {
    /**
     * Resolve the active provider for a customer and service.
     * If $customer is null, returns the global default provider.
     */
    public function resolve(ServiceType $service, ?Customer $customer): object;
    public function resolveWithFallback(ServiceType $service, ?Customer $customer): FallbackChain;
}
```

Resolution logic:
1. Check in-memory cache (per-request)
2. Check Redis cache (5 min TTL)
3. Query `CustomerIntegration` for customer + service, ordered by priority ASC
4. If none found → use global default from `.env`
5. Use `ProviderFactoryRegistry` to create the provider instance

Cache key format: `provider_config:{customer_id}:{service_type}` (avoids collisions between services for the same customer).

Cache invalidation: On `CustomerIntegration` save/delete, invalidate all keys matching `provider_config:{customer_id}:*`.

### TenantContext

```php
class TenantContext {
    public function __construct(private Security $security) {}

    public function getCustomer(): ?Customer {
        $user = $this->security->getUser();
        if ($user instanceof User && $user->getCustomer()) {
            return $user->getCustomer();
        }
        return null; // Admin/Operator without customer → use defaults
    }
}
```

### Transparent Proxy Example

```php
class TenantAwareRouteOptimizer implements RouteOptimizerInterface {
    public function __construct(
        private ProviderResolverInterface $resolver,
        private TenantContext $tenantContext,
    ) {}

    public function optimize(array $vehicles, array $jobs): OptimizationResult {
        // resolve() accepts null customer → returns global default
        $customer = $this->tenantContext->getCustomer();
        $optimizer = $this->resolver->resolve(ServiceType::RouteOptimizer, $customer);
        assert($optimizer instanceof RouteOptimizerInterface);
        return $optimizer->optimize($vehicles, $jobs);
    }
}
```

### FallbackChain

```php
class FallbackChain {
    /** @param object[] $providers Ordered by priority */
    public function __construct(private array $providers) {}

    /**
     * Try each provider in order. Return first successful result.
     * @param callable(object): mixed $operation
     */
    public function execute(callable $operation): mixed {
        $lastException = null;
        foreach ($this->providers as $provider) {
            try {
                return $operation($provider);
            } catch (ProviderUnavailableException $e) {
                $lastException = $e;
                // Log warning: provider failed, trying next
            }
        }
        throw $lastException ?? new \RuntimeException('No providers available');
    }
}
```

### ProviderUnavailableException

```php
class ProviderUnavailableException extends \RuntimeException {
    public function __construct(
        public readonly string $providerType,
        string $message = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message ?: "Provider '{$providerType}' is unavailable", 0, $previous);
    }
}
```

Providers throw this for **transient failures** (network errors, rate limits, API down). Programming errors (`TypeError`, `LogicException`) are NOT caught by `FallbackChain` — they propagate immediately.

## Provider Factory System

### ProviderFactoryInterface

```php
#[AutoconfigureTag('app.provider_factory')]
interface ProviderFactoryInterface {
    public function create(array $config): object;
    public static function providerType(): string;
    public static function serviceType(): ServiceType;
}
```

### ProviderFactoryRegistry

```php
class ProviderFactoryRegistry {
    /** @var array<string, ProviderFactoryInterface> Keyed by providerType */
    private array $factories = [];

    public function __construct(
        #[TaggedIterator('app.provider_factory')]
        iterable $factories,
        private array $defaults, // From .env: service_type => provider_type
    ) {
        foreach ($factories as $factory) {
            $this->factories[$factory::providerType()] = $factory;
        }
    }

    public function create(CustomerIntegration $integration): object {
        $factory = $this->factories[$integration->getProviderType()]
            ?? throw new \InvalidArgumentException("Unknown provider: {$integration->getProviderType()}");
        return $factory->create($integration->getConfig());
    }

    public function createDefault(ServiceType $service): object {
        $providerType = $this->defaults[$service->value]
            ?? throw new \RuntimeException("No default provider for {$service->value}");
        $factory = $this->factories[$providerType]
            ?? throw new \RuntimeException("Default provider factory not found: {$providerType}");
        return $factory->create([]);  // Empty config uses env vars / service defaults
    }

    /** @return array<string, ServiceType> Available providers grouped by service */
    public function getAvailableProviders(): array { /* ... */ }
}
```

### Example Factory

```php
class GoogleDirectionsFactory implements ProviderFactoryInterface {
    public function __construct(private HttpClientInterface $httpClient) {}

    public function create(array $config): RoutingEngineInterface {
        $cfg = GoogleDirectionsConfig::fromArray($config);
        return new GoogleDirectionsEngine($this->httpClient, $cfg);
    }

    public static function providerType(): string { return RoutingProvider::GoogleDirections->value; }
    public static function serviceType(): ServiceType { return ServiceType::RoutingEngine; }
}
```

## New Providers (v1)

### 1. GreedyOptimizer (RouteOptimizerInterface)

PHP-pure implementation using nearest-neighbor + savings algorithm:
- No external dependencies
- Works for small fleets (< 50 stops per vehicle)
- Respects vehicle capacity constraints (weight, volume, parcels)
- Uses Haversine for distance estimation between stops
- Not globally optimal but produces reasonable routes

### 2. HaversineEngine (RoutingEngineInterface)

PHP-pure routing using Haversine formula with road correction factor:
- Distance: Haversine * 1.3 (configurable correction factor for urban areas)
- Duration: distance / average_speed (configurable, default 30 km/h urban)
- No external dependencies, instant response
- Suitable as fallback when no routing API is available

### 3. GoogleDirectionsEngine (RoutingEngineInterface)

Google Maps Directions API integration:
- Real road distances and durations
- Supports waypoints, traffic-aware routing
- Requires API key (per-customer config)
- Rate limiting and cost tracking built in

### 4. WebhookGpsProvider (GpsDeviceProviderInterface)

Generic HTTP webhook receiver:
- Accepts POST with standardized JSON payload: `{device_id, lat, lng, speed, timestamp}`
- Compatible with any GPS device/app that can send HTTP requests
- No infrastructure required (Symfony handles HTTP)
- Stores positions via existing ingestion pipeline

**Note on GpsDeviceProviderInterface:** The current interface has Traccar-specific methods (`login()`, `getSessionCookie()`). As a preparatory step, these methods should be refactored: `login()` and `getSessionCookie()` should be moved to TraccarGpsProvider only (not part of the port). The port should be narrowed to `getDevices()`, `createDevice()`, `getPositions()`, and `isAvailable()`. The `$deviceId` parameter in `getPositions()` should use `string` (unique device identifier) instead of `int` (Traccar internal ID).

### 5. HttpPollingPublisher (RealtimePublisherInterface)

Database-backed polling alternative to SSE:
- Writes events to a `realtime_event` table
- Frontend polls `GET /api/events?since={timestamp}` every N seconds
- Configurable polling interval (default 5s)
- Auto-cleanup of old events (TTL configurable)
- No Mercure hub required

## Services Configuration

### services.yaml changes

```yaml
# Provider Framework
App\Provider\ProviderFactoryRegistry:
    arguments:
        $defaults:
            route_optimizer: '%env(DEFAULT_ROUTE_OPTIMIZER)%'
            routing_engine: '%env(DEFAULT_ROUTING_ENGINE)%'
            gps_provider: '%env(DEFAULT_GPS_PROVIDER)%'
            realtime_publisher: '%env(DEFAULT_REALTIME_PUBLISHER)%'

# Transparent Proxies replace direct aliases
App\RouteOptimization\RouteOptimizerInterface:
    alias: App\Provider\RouteOptimizer\TenantAwareRouteOptimizer

App\Routing\RoutingEngineInterface:
    alias: App\Provider\Routing\TenantAwareRoutingEngine

App\Tracking\GpsDeviceProviderInterface:
    alias: App\Provider\Gps\TenantAwareGpsProvider

App\Realtime\RealtimePublisherInterface:
    alias: App\Provider\Realtime\TenantAwareRealtimePublisher
```

### .env defaults

```env
DEFAULT_ROUTE_OPTIMIZER=vroom
DEFAULT_ROUTING_ENGINE=osrm
DEFAULT_GPS_PROVIDER=traccar
DEFAULT_REALTIME_PUBLISHER=mercure
```

## Admin UI

CRUD in admin panel for managing `CustomerIntegration`:

### List View
- Grouped by Customer
- Shows service type, provider, enabled status, priority
- Quick toggle for enable/disable

### Create/Edit Form
- Select Customer
- Select Service Type → filters available Provider Types
- Select Provider Type → renders dynamic config form (fields depend on provider)
- Config validation on save (DTO validation + optional health check)
- Priority field for fallback ordering

### Controller: `AdminCustomerIntegrationController`
- Standard Symfony form handling
- Uses `ProviderFactoryRegistry::getAvailableProviders()` for dropdown options
- Validates config via provider-specific DTO: on save, resolves the `ProviderFactoryInterface` by `providerType`, calls `create()` to validate the config DTO, catches validation errors and displays them in the form
- Invalidates cache on save (all keys for that customer)

## Database Migration

```sql
CREATE TABLE customer_integration (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(26) NOT NULL UNIQUE,  -- ULID format (matches PublicIdTrait)
    customer_id BIGINT NOT NULL REFERENCES customer(id),
    service_type VARCHAR(30) NOT NULL,
    provider_type VARCHAR(50) NOT NULL,
    config JSONB NOT NULL DEFAULT '{}',
    enabled BOOLEAN NOT NULL DEFAULT true,
    priority SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    UNIQUE (customer_id, service_type, priority)
);

CREATE INDEX idx_ci_customer_service ON customer_integration(customer_id, service_type, enabled);
```

For HttpPollingPublisher:

```sql
CREATE TABLE realtime_event (
    id BIGSERIAL PRIMARY KEY,
    customer_id BIGINT NOT NULL,
    topic VARCHAR(255) NOT NULL,
    data JSONB NOT NULL,
    event_type VARCHAR(50),
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_re_customer_topic_time ON realtime_event(customer_id, topic, created_at);
```

**Tenant isolation for `realtime_event`:** This table will be a Doctrine entity implementing `CustomerScopedEntityInterface`, so the `CustomerTenantFilter` automatically scopes queries. The polling endpoint will only return events for the authenticated user's customer.

## Migration Path

1. **No breaking changes:** Existing customers without `CustomerIntegration` records use global defaults
2. **Backward compatible:** All existing services continue to work unchanged
3. **Incremental adoption:** Customers can be migrated one at a time via admin UI
4. **Deprecated facades unaffected:** `VroomApiClient`, `TraccarApiClient` wrappers continue to work

## Testing Strategy

- Unit tests for each new provider (GreedyOptimizer, HaversineEngine, etc.)
- Unit tests for ProviderResolver with mocked repository
- Unit tests for FallbackChain behavior
- Integration tests for TenantAware proxies with real DI container
- Functional tests for admin CRUD
- Existing tests remain unchanged (they use NullRouteOptimizer, NullRoutingEngine, etc.)

## Out of Scope (Future)

- Provider usage analytics/billing per customer
- A/B testing between providers
- Provider health monitoring dashboard
- Automatic failover with circuit breaker pattern
- Credential encryption at rest (Symfony Secrets integration)
- PusherPublisher, AblyPublisher (second sprint)
- GoogleOrToolsOptimizer (requires Python microservice or API)
