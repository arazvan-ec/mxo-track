# Implementation Plan: User-Configurable Providers

**Goal:** Per-Customer configurable service providers with transparent proxy pattern, fallback chains, and 5 new provider implementations.

**Design Spec:** `docs/superpowers/specs/2026-03-11-user-configurable-providers-design.md`

**Architecture:** Symfony 7.4 LTS, PHP 8.4, Doctrine ORM 3.x, PostgreSQL 16, Redis 7

**Branch:** `claude/user-configurable-providers-VqMvb`

---

## File Structure

```
backend/src/
├── Provider/                                    # NEW: Provider framework
│   ├── ServiceType.php                          # Enum
│   ├── ProviderUnavailableException.php         # Exception for fallback
│   ├── ProviderResolverInterface.php            # Port
│   ├── ProviderResolver.php                     # Implementation
│   ├── CachedProviderResolver.php               # Decorator with cache
│   ├── ProviderFactoryInterface.php             # Factory port
│   ├── ProviderFactoryRegistry.php              # Registry of factories
│   ├── FallbackChain.php                        # Fallback execution
│   ├── TenantContext.php                        # Current tenant resolver
│   │
│   ├── RouteOptimizer/
│   │   ├── RouteOptimizerProvider.php           # Enum
│   │   ├── TenantAwareRouteOptimizer.php        # Proxy
│   │   ├── VroomFactory.php                     # Factory
│   │   ├── GreedyOptimizer.php                  # NEW provider
│   │   ├── GreedyOptimizerFactory.php           # Factory
│   │   └── GreedyOptimizerConfig.php            # Config DTO
│   │
│   ├── Routing/
│   │   ├── RoutingProvider.php                  # Enum
│   │   ├── TenantAwareRoutingEngine.php         # Proxy
│   │   ├── OsrmFactory.php                      # Factory
│   │   ├── HaversineEngine.php                  # NEW provider
│   │   ├── HaversineFactory.php                 # Factory
│   │   ├── HaversineConfig.php                  # Config DTO
│   │   ├── GoogleDirectionsEngine.php           # NEW provider
│   │   ├── GoogleDirectionsFactory.php          # Factory
│   │   └── GoogleDirectionsConfig.php           # Config DTO
│   │
│   ├── Gps/
│   │   ├── GpsProviderType.php                  # Enum
│   │   ├── TenantAwareGpsProvider.php           # Proxy
│   │   ├── TraccarFactory.php                   # Factory
│   │   ├── WebhookGpsProvider.php               # NEW provider
│   │   └── WebhookGpsFactory.php                # Factory
│   │
│   └── Realtime/
│       ├── RealtimeProviderType.php             # Enum
│       ├── TenantAwareRealtimePublisher.php     # Proxy
│       ├── MercureFactory.php                   # Factory
│       ├── HttpPollingPublisher.php             # NEW provider
│       ├── HttpPollingFactory.php               # Factory
│       └── HttpPollingConfig.php                # Config DTO
│
├── Entity/
│   ├── CustomerIntegration.php                  # NEW entity
│   └── RealtimeEvent.php                        # NEW entity (for polling)
│
├── Repository/
│   ├── CustomerIntegrationRepository.php        # NEW
│   └── RealtimeEventRepository.php              # NEW
│
├── Controller/
│   ├── Admin/CustomerIntegrationController.php  # NEW admin CRUD
│   └── Api/V1/EventPollingController.php        # NEW polling endpoint
│
├── Form/
│   └── CustomerIntegrationType.php              # NEW form

backend/tests/
├── Unit/
│   ├── Provider/
│   │   ├── ProviderResolverTest.php
│   │   ├── CachedProviderResolverTest.php
│   │   ├── FallbackChainTest.php
│   │   ├── ProviderFactoryRegistryTest.php
│   │   ├── TenantContextTest.php
│   │   ├── RouteOptimizer/
│   │   │   └── GreedyOptimizerTest.php
│   │   ├── Routing/
│   │   │   ├── HaversineEngineTest.php
│   │   │   └── GoogleDirectionsEngineTest.php
│   │   ├── Gps/
│   │   │   └── WebhookGpsProviderTest.php
│   │   └── Realtime/
│   │       └── HttpPollingPublisherTest.php
```

---

## Tasks

### Phase 1: Foundation (Provider Framework)

#### Task 1: ServiceType enum and ProviderUnavailableException
- [ ] Write test: `ServiceType` enum has 4 cases with correct string values
- [ ] Write test: `ProviderUnavailableException` stores providerType and message
- [ ] Implement `backend/src/Provider/ServiceType.php`
- [ ] Implement `backend/src/Provider/ProviderUnavailableException.php`
- [ ] Verify tests pass
- [ ] Commit

#### Task 2: Provider enums (one per service)
- [ ] Write test: Each enum has the correct cases and `value` strings
- [ ] Implement `backend/src/Provider/RouteOptimizer/RouteOptimizerProvider.php`
- [ ] Implement `backend/src/Provider/Routing/RoutingProvider.php`
- [ ] Implement `backend/src/Provider/Gps/GpsProviderType.php`
- [ ] Implement `backend/src/Provider/Realtime/RealtimeProviderType.php`
- [ ] Verify tests pass
- [ ] Commit

#### Task 3: CustomerIntegration entity + migration
- [ ] Write test: Entity can be created with all required fields, implements `CustomerScopedEntityInterface`, uses `PublicIdTrait`
- [ ] Implement `backend/src/Entity/CustomerIntegration.php`:
  - `#[ORM\HasLifecycleCallbacks]`
  - Implements `CustomerScopedEntityInterface`
  - Uses `PublicIdTrait`
  - Fields: customer (ManyToOne), serviceType (enum), providerType (string), config (json), enabled (bool), priority (smallint), createdAt, updatedAt
  - UniqueConstraint on (customer_id, service_type, priority)
- [ ] Implement `backend/src/Repository/CustomerIntegrationRepository.php`:
  - Method: `findActiveByCustomerAndService(Customer $customer, ServiceType $service): array` — returns enabled integrations ordered by priority ASC
- [ ] Generate Doctrine migration: `php bin/console doctrine:migrations:diff`
- [ ] Verify tests pass
- [ ] Commit

#### Task 4: ProviderFactoryInterface + ProviderFactoryRegistry
- [ ] Write test for `ProviderFactoryRegistry`:
  - Test `create()` returns correct provider for a given `CustomerIntegration`
  - Test `create()` throws on unknown provider type
  - Test `createDefault()` uses env-configured defaults
  - Test `getAvailableProviders()` returns grouped providers
- [ ] Implement `backend/src/Provider/ProviderFactoryInterface.php`:
  ```php
  #[AutoconfigureTag('app.provider_factory')]
  interface ProviderFactoryInterface {
      public function create(array $config): object;
      public static function providerType(): string;
      public static function serviceType(): ServiceType;
  }
  ```
- [ ] Implement `backend/src/Provider/ProviderFactoryRegistry.php`:
  - Constructor receives `#[TaggedIterator('app.provider_factory')]` and `$defaults` array
  - Methods: `create(CustomerIntegration)`, `createDefault(ServiceType)`, `getAvailableProviders()`
- [ ] Verify tests pass
- [ ] Commit

#### Task 5: TenantContext
- [ ] Write test:
  - Returns Customer when user has customer association
  - Returns null for admin users without customer
  - Returns null when no user is authenticated
- [ ] Implement `backend/src/Provider/TenantContext.php`:
  - Inject `Security`
  - `getCustomer(): ?Customer` — extracts customer from current user
- [ ] Verify tests pass
- [ ] Commit

#### Task 6: ProviderResolver
- [ ] Write test for `ProviderResolver`:
  - Test resolves provider from CustomerIntegration
  - Test falls back to global default when no integration exists
  - Test handles null customer (returns default)
  - Test `resolveWithFallback()` returns FallbackChain with multiple providers
- [ ] Implement `backend/src/Provider/ProviderResolverInterface.php`
- [ ] Implement `backend/src/Provider/ProviderResolver.php`:
  - Inject `CustomerIntegrationRepository` and `ProviderFactoryRegistry`
  - `resolve(ServiceType, ?Customer): object`
  - `resolveWithFallback(ServiceType, ?Customer): FallbackChain`
- [ ] Verify tests pass
- [ ] Commit

#### Task 7: FallbackChain
- [ ] Write test:
  - Returns result from first provider when it succeeds
  - Falls back to second provider when first throws `ProviderUnavailableException`
  - Does NOT catch non-ProviderUnavailableException errors (lets them propagate)
  - Throws last exception when all providers fail
- [ ] Implement `backend/src/Provider/FallbackChain.php`
- [ ] Verify tests pass
- [ ] Commit

#### Task 8: CachedProviderResolver
- [ ] Write test:
  - Returns cached result on second call (same request)
  - Delegates to inner resolver on first call
- [ ] Implement `backend/src/Provider/CachedProviderResolver.php`:
  - Decorator pattern wrapping `ProviderResolver`
  - In-memory cache (per-request, array-based) — start simple, add Redis later if needed
- [ ] Verify tests pass
- [ ] Commit

### Phase 2: Transparent Proxies + Factories for Existing Providers

#### Task 9: VroomFactory + TenantAwareRouteOptimizer
- [ ] Write test for `VroomFactory`:
  - Creates `VroomRouteOptimizer` with config URL
  - Creates with empty config (uses env var)
- [ ] Write test for `TenantAwareRouteOptimizer`:
  - Delegates to resolved provider
  - Uses default when no customer in context
- [ ] Implement `backend/src/Provider/RouteOptimizer/VroomFactory.php`
- [ ] Implement `backend/src/Provider/RouteOptimizer/TenantAwareRouteOptimizer.php`
- [ ] Update `services.yaml`: alias `RouteOptimizerInterface` → `TenantAwareRouteOptimizer`
- [ ] Verify tests pass
- [ ] Commit

#### Task 10: OsrmFactory + TenantAwareRoutingEngine
- [ ] Write test for `OsrmFactory`:
  - Creates `OsrmRoutingEngine` with config URL
  - Creates with empty config (uses env var)
- [ ] Write test for `TenantAwareRoutingEngine`:
  - Delegates `route()` and `routeWithWaypoints()` to resolved provider
- [ ] Implement `backend/src/Provider/Routing/OsrmFactory.php`
- [ ] Implement `backend/src/Provider/Routing/TenantAwareRoutingEngine.php`
- [ ] Update `services.yaml`: alias `RoutingEngineInterface` → `TenantAwareRoutingEngine`
- [ ] Verify tests pass
- [ ] Commit

#### Task 11: TraccarFactory + TenantAwareGpsProvider
- [ ] Write test for `TraccarFactory`:
  - Creates `TraccarGpsProvider` with config (baseUrl, username, password)
  - Creates with empty config (uses env vars)
- [ ] Write test for `TenantAwareGpsProvider`:
  - Delegates all methods to resolved provider
- [ ] Implement `backend/src/Provider/Gps/TraccarFactory.php`
- [ ] Implement `backend/src/Provider/Gps/TenantAwareGpsProvider.php`
- [ ] Update `services.yaml`: alias `GpsDeviceProviderInterface` → `TenantAwareGpsProvider`
- [ ] Verify tests pass
- [ ] Commit

#### Task 12: MercureFactory + TenantAwareRealtimePublisher
- [ ] Write test for `MercureFactory`:
  - Creates `MercurePublisher` instance
- [ ] Write test for `TenantAwareRealtimePublisher`:
  - Delegates `publish()` and `publishBatch()` to resolved provider
- [ ] Implement `backend/src/Provider/Realtime/MercureFactory.php`
- [ ] Implement `backend/src/Provider/Realtime/TenantAwareRealtimePublisher.php`
- [ ] Update `services.yaml`: alias `RealtimePublisherInterface` → `TenantAwareRealtimePublisher`
- [ ] Verify tests pass
- [ ] Commit

### Phase 3: New Providers

#### Task 13: HaversineEngine
- [ ] Write test:
  - `route()` returns correct Haversine distance * correction factor
  - `route()` returns estimated duration based on average speed
  - `routeWithWaypoints()` returns correct cumulative distances and durations
  - Correction factor and average speed are configurable
  - Known pair: Madrid Sol (40.4168, -3.7038) → Atocha (40.4065, -3.6933) ≈ 1.3 km Haversine → ~1.7 km with 1.3 factor
- [ ] Implement `backend/src/Provider/Routing/HaversineConfig.php`:
  - `correctionFactor` (float, default 1.3)
  - `averageSpeedKmh` (float, default 30.0)
- [ ] Implement `backend/src/Provider/Routing/HaversineEngine.php`:
  - Implements `RoutingEngineInterface`
  - Pure PHP Haversine formula
  - Applies correction factor for road distance estimation
  - Duration = distance / averageSpeed
- [ ] Implement `backend/src/Provider/Routing/HaversineFactory.php`
- [ ] Verify tests pass
- [ ] Commit

#### Task 14: GreedyOptimizer
- [ ] Write test:
  - With 1 vehicle, 3 jobs: returns 1 route with 3 stops in nearest-neighbor order
  - With 2 vehicles, 5 jobs: distributes across vehicles respecting capacity
  - Respects weight capacity constraints
  - Respects volume capacity constraints
  - Returns unassigned jobs when capacity exceeded
  - Edge case: no jobs → empty result
  - Edge case: no vehicles → all jobs unassigned
- [ ] Implement `backend/src/Provider/RouteOptimizer/GreedyOptimizerConfig.php`:
  - No required fields (pure PHP, no external deps)
- [ ] Implement `backend/src/Provider/RouteOptimizer/GreedyOptimizer.php`:
  - Implements `RouteOptimizerInterface`
  - Algorithm: nearest-neighbor with capacity-first assignment
  - Uses Haversine for inter-stop distances
  - Returns `OptimizationResult` with `OptimizedRoute[]` and unassigned jobs
- [ ] Implement `backend/src/Provider/RouteOptimizer/GreedyOptimizerFactory.php`
- [ ] Verify tests pass
- [ ] Commit

#### Task 15: GoogleDirectionsEngine
- [ ] Write test (with mocked HTTP client):
  - `route()` calls Google Directions API with correct parameters
  - `route()` parses response: extracts distance (meters→km) and duration (seconds)
  - `routeWithWaypoints()` handles multiple legs
  - Throws `ProviderUnavailableException` on HTTP errors (timeout, 5xx)
  - Handles API error responses (ZERO_RESULTS, OVER_QUERY_LIMIT)
- [ ] Implement `backend/src/Provider/Routing/GoogleDirectionsConfig.php`:
  - `apiKey` (required), `region` (default 'es'), `avoidTolls` (default false)
- [ ] Implement `backend/src/Provider/Routing/GoogleDirectionsEngine.php`:
  - Implements `RoutingEngineInterface`
  - Uses Symfony HttpClient
  - Google Directions API: `https://maps.googleapis.com/maps/api/directions/json`
  - Converts response legs to `RouteResult` / `MultiWaypointRouteResult`
- [ ] Implement `backend/src/Provider/Routing/GoogleDirectionsFactory.php`
- [ ] Verify tests pass
- [ ] Commit

#### Task 16: WebhookGpsProvider
- [ ] Write test:
  - `getDevices()` returns devices from database (vehicles with webhook source)
  - `getPositions()` returns positions stored via webhook
  - `createDevice()` creates a Vehicle entry marked as webhook-sourced
  - `isAvailable()` always returns true
  - `login()` is no-op, `getSessionCookie()` returns null
- [ ] Implement `backend/src/Provider/Gps/WebhookGpsProvider.php`:
  - Implements `GpsDeviceProviderInterface`
  - Reads from existing VehiclePosition/VehicleLastPosition entities
  - `login()` → no-op, `getSessionCookie()` → null
- [ ] Implement `backend/src/Provider/Gps/WebhookGpsFactory.php`
- [ ] Verify tests pass
- [ ] Commit

#### Task 17: HttpPollingPublisher + RealtimeEvent entity
- [ ] Write test for `RealtimeEvent` entity:
  - Can be created with customer, topic, data, eventType
  - Implements `CustomerScopedEntityInterface`
- [ ] Write test for `HttpPollingPublisher`:
  - `publish()` persists a `RealtimeEvent` entity
  - `publishBatch()` persists multiple events
- [ ] Implement `backend/src/Entity/RealtimeEvent.php`:
  - Uses `PublicIdTrait`, implements `CustomerScopedEntityInterface`
  - Fields: customer, topic, data (json), eventType, createdAt
- [ ] Implement `backend/src/Repository/RealtimeEventRepository.php`:
  - `findSince(Customer $customer, string $topic, \DateTimeImmutable $since): array`
  - `deleteOlderThan(\DateTimeImmutable $before): int` (cleanup)
- [ ] Implement `backend/src/Provider/Realtime/HttpPollingPublisher.php`:
  - Implements `RealtimePublisherInterface`
  - Persists events to `RealtimeEvent` table
  - Needs TenantContext to assign customer_id
- [ ] Implement `backend/src/Provider/Realtime/HttpPollingFactory.php`
- [ ] Generate Doctrine migration for `RealtimeEvent`
- [ ] Verify tests pass
- [ ] Commit

#### Task 18: Event polling API endpoint
- [ ] Write test:
  - `GET /api/v1/events?since=2026-01-01T00:00:00Z&topic=/vehicles/xxx/position` returns events
  - Respects tenant isolation (only returns events for authenticated customer)
  - Returns 401 for unauthenticated requests
- [ ] Implement `backend/src/Controller/Api/V1/EventPollingController.php`:
  - `#[Route('/api/v1/events')]`
  - Query params: `since` (ISO 8601), `topic` (optional filter)
  - Returns JSON array of events
- [ ] Verify tests pass
- [ ] Commit

### Phase 4: Admin UI

#### Task 19: CustomerIntegration form + admin CRUD
- [ ] Implement `backend/src/Form/CustomerIntegrationType.php`:
  - Fields: customer (EntityType), serviceType (EnumType), providerType (ChoiceType, dynamic), config (TextareaType for JSON), enabled (CheckboxType), priority (IntegerType)
- [ ] Implement `backend/src/Controller/Admin/CustomerIntegrationController.php`:
  - `index()`: List all integrations, grouped by customer
  - `new()`: Create form
  - `edit(string $publicId)`: Edit form
  - `delete(string $publicId)`: Delete with CSRF
  - Cache invalidation on save/delete
- [ ] Create Twig templates:
  - `templates/admin/customer_integration/index.html.twig`
  - `templates/admin/customer_integration/form.html.twig`
- [ ] Add navigation link in admin layout
- [ ] Verify manually (no automated test for UI)
- [ ] Commit

### Phase 5: Configuration + Integration

#### Task 20: services.yaml wiring + .env defaults
- [ ] Add to `.env`:
  ```
  DEFAULT_ROUTE_OPTIMIZER=vroom
  DEFAULT_ROUTING_ENGINE=osrm
  DEFAULT_GPS_PROVIDER=traccar
  DEFAULT_REALTIME_PUBLISHER=mercure
  ```
- [ ] Update `services.yaml`:
  - Register `ProviderFactoryRegistry` with `$defaults` from env
  - Change all 4 interface aliases to TenantAware proxies
  - Ensure all factories are tagged (should be automatic with `AutoconfigureTag`)
- [ ] Run existing test suite to verify no regressions
- [ ] Commit

#### Task 21: Fixtures for testing
- [ ] Add `CustomerIntegration` fixtures:
  - Demo customer with Haversine routing + Greedy optimizer (zero-infra setup)
  - Another customer with default providers (no integrations = uses defaults)
- [ ] Verify fixtures load: `php bin/console doctrine:fixtures:load -n`
- [ ] Commit

### Phase 6: Final Verification

#### Task 22: Full test suite + lint
- [ ] Run `make lint` — zero errors
- [ ] Run `php bin/phpunit` — all tests pass
- [ ] Run `php bin/console doctrine:schema:validate` — schema is in sync
- [ ] Commit any final fixes
- [ ] Push to branch

---

## Commands Reference

```bash
# Run all tests
cd backend && php bin/phpunit

# Run specific test
php bin/phpunit tests/Unit/Provider/ProviderResolverTest.php

# Generate migration
php bin/console doctrine:migrations:diff

# Run migrations
php bin/console doctrine:migrations:migrate -n

# Validate schema
php bin/console doctrine:schema:validate

# Lint
make lint

# Load fixtures
php bin/console doctrine:fixtures:load -n
```
