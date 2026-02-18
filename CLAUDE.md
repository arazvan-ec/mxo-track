# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**transporte-tracking** (mxo-track) — Logistics tracking portal built on **Symfony 7.4 LTS** (strict lock, no 8.x components). Monorepo with `backend/` (Symfony), `infra/` (server provisioning), and `docs/`.

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

# E2E boot check (starts Docker services)
make e2e-symfony

# Phase validation script
bash scripts/phase_flow_validate.sh

# Verify Symfony 7.4 lock is respected
bash scripts/check_symfony_74_lock.sh
```

### Docker (local development)

No se usa PHP local. Todo el desarrollo se hace dentro del contenedor Docker `app`.

```bash
# Construir y levantar todos los servicios
docker compose -f docker-compose.local.yml up -d --build

# Entrar al contenedor para trabajar
docker compose -f docker-compose.local.yml exec app bash

# Dentro del contenedor: instalar, crear esquema, cargar fixtures y servir
composer install
php bin/console doctrine:schema:create          # primera vez (DB vacía)
php bin/console doctrine:migrations:migrate -n  # siguientes veces
php bin/console doctrine:fixtures:load -n
php -S 0.0.0.0:8000 -t public
```

Backend accesible en **http://localhost:8000**.

Services: `app` (PHP 8.4, puerto 8000), `db` (postgres:16, puerto 5432), `redis` (redis:7, puerto 6379), `mercure` (dunglas/mercure, puerto 3000), `traccar` (traccar/traccar, puerto 8082 API/Web + 5055 GPS).

Traccar usa H2 embebida (sin MariaDB) — suficiente para desarrollo. Al primer arranque crea usuario admin (`admin`/`admin`). La app se conecta vía `TRACCAR_BASE_URL=http://traccar:8082`.

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

- `TraccarApiClient`: HTTP client for Traccar REST API (devices, positions)
- `TraccarStreamCommand`: Polling-based position ingestion with backfill (--once, --sleep=5)
- `TraccarSyncDevicesCommand`: Syncs Traccar devices to local Vehicle entities
- `TraccarIngestionService`: Processes and stores position data

### Mercure Realtime

- Topics: `/vehicles/{public_id}/position`, `/operator/fleet`, `/customers/{id}/routes`, `/customers/{id}/shipments`
- `MercureJwtFactory` generates subscriber tokens
- `MercureTokenController` provides tokens to frontend
- `TopicResolver` handles topic authorization

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
