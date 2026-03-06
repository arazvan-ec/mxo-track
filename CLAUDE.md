# Para optimizar una ruta

1. Cada entrega debe tener una configuracion de volumen y peso
2. Tambien necesitamos la configuracion de volumen y peso que entra en cada vehiculo

# Demo para cliente

1. CSV para importar
2. Con ese CSV tenemos que crear X rutas, cada vehiculo puede hacer x entregas, poder configurar antes de acceptar la ruta

# CLAUDE.md: claude --resume 2a057aa1-7456-4257-ab81-debee0c6a901 <> eliminar customer vehicle -> seguir

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**transporte-tracking** (mxo-track) — Logistics tracking portal built on **Symfony 7.4 LTS** (strict lock, no 8.x components). Monorepo with `backend/` (Symfony) and `docs/`. Deployed on Railway.

The system tracks vehicles via Traccar integration, manages delivery routes with driver proof-of-delivery (POD), and provides real-time position updates via Mercure. Multi-tenant via `customer_id` Doctrine SQL filter.

## Tech Stack

- PHP 8.4 (Docker image: `php:8.4-cli-bookworm`), Symfony 7.4 LTS (Flex + recipes)
- PostgreSQL 16, Redis 7 (sessions), Mercure (realtime SSE)
- Doctrine ORM 3.x with attribute mapping (requires `naming_strategy: underscore_number_aware` in doctrine.yaml)
- Twig + Turbo (UX Turbo) for frontend
- Traccar for GPS device tracking

## Common Commands

```bash
# Install dependencies
cd backend && composer install

# Verify Symfony is working
php bin/console about

# Run migrations
php bin/console doctrine:migrations:migrate -n

# Load fixtures (includes admin user)
php bin/console doctrine:fixtures:load -n

# PHP syntax lint (all src files)
make lint
```

### Docker (local development)

No se usa PHP local. Todo el desarrollo se hace dentro del contenedor Docker `app`. La imagen es `php:8.4-cli-bookworm` (sin Apache/nginx), por lo que el servidor web se arranca manualmente con el built-in server de PHP.

#### Arranque rápido (desde la raíz del proyecto)

```bash
# 1. Construir y levantar todos los servicios (db, redis, mercure, traccar)
docker compose -f docker-compose.local.yml up -d --build

# 2. Entrar al contenedor app
docker compose -f docker-compose.local.yml exec app bash

# 3. Dentro del contenedor: instalar deps, preparar DB y arrancar servidor
composer install
php bin/console doctrine:schema:create          # primera vez (DB vacía)
php bin/console doctrine:migrations:migrate -n  # siguientes veces
php bin/console doctrine:fixtures:load -n
php -S 0.0.0.0:8000 -t public                  # arranca el servidor web
```

#### Arranque en una línea (sin entrar al contenedor)

```bash
docker compose -f docker-compose.local.yml up -d --build
docker compose -f docker-compose.local.yml exec app bash -c \
  "composer install && php bin/console doctrine:migrations:migrate -n && php -S 0.0.0.0:8000 -t public"
```

#### URLs locales

| Servicio | URL | Notas |
|----------|-----|-------|
| Backend (Symfony) | http://localhost:8000 | Built-in PHP server |
| Traccar Web UI / API | http://localhost:8082 | Credenciales: `admin`/`admin` |
| Mercure Hub | http://localhost:3000/.well-known/mercure | SSE realtime |
| PostgreSQL (app) | localhost:5432 | User: `mxo`, DB: `mxo_track` |
| PostgreSQL (traccar) | localhost:5433 | User: `traccar`, DB: `traccar` |
| Redis | localhost:6379 | Sesiones |

#### Notas

- El servidor PHP built-in es **single-threaded** y solo para desarrollo. No usar en producción.
- Traccar usa **PostgreSQL dedicado** (`traccar_db`, puerto 5433 en host). La configuración se monta desde `docker/traccar-local/traccar.xml`. **No crea usuario admin automáticamente** (ver sección "Inicialización de Traccar" más abajo). La app se conecta vía `TRACCAR_BASE_URL=http://traccar:8082`.
- Si se cierra la terminal, el servidor PHP se detiene. Para arrancarlo de nuevo: entrar al contenedor y ejecutar `php -S 0.0.0.0:8000 -t public`.

Services: `app` (PHP 8.4, puerto 8000), `db` (postgres:16, puerto 5432), `redis` (redis:7, puerto 6379), `mercure` (dunglas/mercure, puerto 3000), `traccar` (traccar/traccar, puerto 8082 API/Web + 5055 GPS), `traccar_db` (postgres:16, puerto 5433 — BD dedicada para Traccar).

## Architecture

### Entity Identity Pattern (mandatory)

All entities (except `CustomerVehicle`) use `PublicIdTrait` which provides:
- **Internal PK**: `BIGINT` auto-increment (`id`) — used for joins, internal processing
- **Public ID**: `ULID` (`public_id`) — exposed in APIs, URLs, Mercure topics

**Never expose internal `id` in public APIs.** Public endpoints use `{publicId}` route parameters. In Driver API payloads, shipment references use `shipment_public_id` (not `shipment_id`).

### Multi-Tenant Isolation

- `CustomerTenantFilter` (Doctrine SQL filter) scopes queries by `customer_id`
- Entities implement `CustomerScopedEntityInterface` to opt-in to filtering
- `DoctrineCustomerFilterSubscriber` auto-enables filter for `ROLE_CUSTOMER` and `ROLE_DRIVER` users with a customer association
- Admin/Operator users bypass the filter

### Role Hierarchy

```
ROLE_ADMIN > ROLE_OPERATOR > ROLE_CUSTOMER
ROLE_ADMIN > ROLE_DRIVER
```

Defined in `UserRole` enum. Access control: `/admin` requires ADMIN or OPERATOR; `/driver` and `/api/driver` require ADMIN or DRIVER.

### Key Domain Concepts

- **Vehicle / VehiclePosition / VehicleLastPosition**: GPS tracking, positions from Traccar
- **Route / RouteStop**: Delivery routes assigned to drivers, stops with sequence
- **Shipment / ShipmentEvent**: Shipment lifecycle tracked via events (DELIVERED, EXCEPTION, etc.)
- **Pod** (Proof of Delivery): Linked to RouteStop, stores recipient ID and driver confirmation
- **DriverAction**: Idempotency tracking for driver operations via `clientActionId`
- **AuditLog**: Structured audit trail for security-sensitive operations

### Traccar Integration

- `TraccarApiClient`: HTTP client for Traccar REST API (devices, positions, createDevice)
- `TraccarStreamCommand`: Polling-based position ingestion with backfill (--once, --sleep=5)
- `TraccarSyncDevicesCommand`: Syncs Traccar devices to local Vehicle entities
- `TraccarIngestionService`: Processes and stores position data
- `SimulateGpsCommand`: Simula posiciones GPS para desarrollo (ver sección dedicada abajo)

#### Inicialización de Traccar (primer arranque)

Traccar 6.x con H2 embebida arranca con la DB vacía y **sin usuario admin**. El endpoint `GET /api/server` devuelve `"newServer": true`. Hay que registrar manualmente el primer usuario:

```bash
# Desde dentro del contenedor app:
curl -s -X POST 'http://traccar:8082/api/users' \
  -H 'Content-Type: application/json' \
  -d '{"name":"admin","email":"admin","password":"admin"}'
```

Notas importantes:
- El primer usuario creado recibe `administrator: true` automáticamente.
- **No incluir** el campo `"administrator"` en el JSON — provoca un `NullPointerException` porque Traccar intenta verificar permisos antes de que exista ningún usuario.
- Después de esto, login funciona normalmente via `POST /api/session` con `email=admin&password=admin`.
- Si Traccar se recrea (volumen `traccar_data` borrado), hay que repetir este paso.

#### Simulación GPS para desarrollo

El comando `app:dev:simulate-gps` crea devices en Traccar, envía posiciones simuladas y opcionalmente las ingesta en Symfony:

```bash
# Dentro del contenedor app:
php bin/console app:dev:simulate-gps --points=10 --interval=1 --ingest
```

Opciones: `--points=N` (posiciones a enviar), `--interval=N` (segundos entre cada una), `--ingest` (ingestar en Symfony al terminar).

Flujo interno:
1. Busca un Vehicle activo (preferencia por nombre con "Demo")
2. Crea device en Traccar via API REST si no existe (uniqueId: `sim-{nombre}`)
3. Actualiza `Vehicle.traccarDeviceId` en la DB local
4. Envía posiciones al protocolo OsmAnd de Traccar (puerto 5055)
5. Si `--ingest`: espera 3s y llama a `TraccarIngestionService`

Ruta simulada: circuito por el centro de Madrid (Sol → Gran Vía → Plaza España → Palacio Real → Puerta de Toledo → Atocha → Retiro → Cibeles → Sol).

#### Tracking en vivo (Mercure + Traccar)

Para ver el vehículo moverse en tiempo real en `/fleet/map`, se necesitan **dos procesos simultáneos**:

```bash
# 1. Arrancar el stream que lee Traccar y publica a Mercure
docker compose -f docker-compose.local.yml exec -T -d app php bin/console app:traccar:stream --sleep=2

# 2. Simular movimiento GPS (~2 minutos)
docker compose -f docker-compose.local.yml exec -T app php bin/console app:dev:simulate-gps --points=120 --interval=1
```

**Nota**: `--ingest` de `simulate-gps` hace ingesta batch al final, no en tiempo real. Para tracking en vivo, usar `app:traccar:stream` en paralelo.

### Mercure Realtime

- Topics: `/vehicles/{public_id}/position`, `/operator/fleet`, `/customers/{id}/routes`, `/customers/{id}/shipments`
- `MercureJwtFactory` generates subscriber tokens (HS256)
- `MercureTokenController` provides tokens to frontend via `mercureAuthorization` cookie (just the JWT, no "Bearer" prefix)
- `TopicResolver` handles topic authorization
- Publisher config in `mercure.yaml` requires `publish: ['*']` to authorize publishing to all topics
- CORS: Mercure hub must use specific origin (`cors_origins http://localhost:8000`), not `*`, because `EventSource` uses `withCredentials: true`
- All JWT keys (publisher + subscriber) must match between `docker-compose.local.yml` `app` service and `mercure` service

### Session & Security

- Sessions stored in Redis with prefix `sess:transporte:`
- Login via `form_login` with CSRF and rate limiting (5 attempts)
- `SecurityHeadersSubscriber` adds X-Frame-Options, CSP, etc.
- `UserChecker` validates user is active before authentication

## Conventions

- All PHP files use `declare(strict_types=1)`
- Doctrine mappings via PHP attributes (not XML/YAML)
- Doctrine ORM 3.x does not default to snake_case — `naming_strategy: underscore_number_aware` is required in `doctrine.yaml` so that column names match `UniqueConstraint` references (e.g. `publicId` property maps to `public_id` column)
- Controllers use attribute routing
- API error responses via `ApiErrorResponder` (consistent error format)
- DTOs in `src/Dto/` with `fromArray()` factory + Symfony Validator constraints
- Symfony 7.4 lock enforced: `extra.symfony.require=7.4.*` in composer.json, `conflict` for `>=8.0`
