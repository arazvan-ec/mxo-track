# AGENTS.md — Instrucciones para Subagentes

> Este archivo da contexto enfocado a subagentes (Agent tool) para que arranquen productivos sin explorar.

## Regla #1: Lee el Manifest Primero

**ANTES de usar Glob, Grep, o Bash para explorar el codebase, lee `docs/codebase-manifest.md`.**

El manifest tiene: counts, entity list, enum list, bounded contexts, directory trees, service map, entity relationships, y route map. Si el dato está ahí, úsalo directamente.

## Proyecto

**mxo-track** — Portal de logística y tracking. Symfony 7.4 (PHP 8.4) + PostgreSQL + Redis + Mercure + Traccar.

Monorepo: `backend/` (Symfony), `frontend/` (React), `ml-service/` (FastAPI), `docker/`, `scripts/`.

## Convenciones Clave

- `declare(strict_types=1)` en todo archivo PHP
- Doctrine mappings via PHP attributes (no XML/YAML)
- Doctrine ORM 3.x: `naming_strategy: underscore_number_aware`
- Controllers: attribute routing (`#[Route(...)]`)
- DTOs en `src/Dto/` con `fromArray()` factory + Symfony Validator
- Entidades: BIGINT `id` (interno) + ULID `public_id` (APIs) via `PublicIdTrait`
- **NUNCA** exponer `id` interno en APIs públicas
- Multi-tenancy: `CustomerTenantFilter` + `CustomerScopedEntityInterface`

## Bounded Contexts

| Contexto | Path | Tipo | Regla |
|----------|------|------|-------|
| Route Planning | `src/Domain/Route/`, `src/Entity/Route*` | Critico | Codigo nuevo: DDD puro |
| Shipment/Delivery | `src/Entity/Shipment*`, `src/Entity/Pod*` | Critico | Codigo nuevo: DDD puro |
| Route Optimization | `src/RouteOptimization/`, `src/Provider/RouteOptimizer/` | Critico | Ya bien separado |
| MapView | `src/Domain/MapView/` | Critico | DDD puro |
| Identity/Auth | `src/Entity/User.php`, `src/Security/` | Pragmatico | Symfony standard |
| Tenant Management | `src/Entity/Customer*.php` | Pragmatico | Symfony standard |
| Fleet | `src/Entity/Vehicle*.php`, `src/Entity/Driver*` | Pragmatico | Symfony standard |
| Notifications | `src/Notification/`, `src/Entity/Notification*` | Pragmatico | Symfony standard |

**DDD puro** = Entidades POPO sin `#[ORM\...]`, interfaces de repo en Domain, implementaciones en Infrastructure.

**Pragmatico** = Entidades en `src/Entity/` con ORM attributes. Aceptable.

## Estructura de Directorios Clave

```
backend/src/
  Application/     # Application services (facades)
  Command/         # Console commands
  Controller/      # Admin/, Api/, Customer/, Operator/
  Domain/          # DDD: Event/, MapView/, Route/
  Dto/             # Data transfer objects
  Entity/          # Doctrine entities (pragmatic contexts)
  Enum/            # Business enums
  Infrastructure/  # DDD implementations (MapView/, Route/)
  Provider/        # Factory + Strategy pattern (Gps, Realtime, RouteOptimizer, Routing)
  Service/         # Domain/infra services
```

## Knowledge Modules

Si necesitas detalle mas alla del manifest:

| Tema | Archivo |
|------|---------|
| Entidades, relaciones, traits | `docs/knowledge/domain-model.md` |
| Arquitectura, DDD, bounded contexts | `docs/knowledge/architecture-ddd.md` |
| Providers, factories, per-tenant | `docs/knowledge/provider-framework.md` |
| Controllers, DTOs, APIs | `docs/knowledge/api-surface.md` |
| Route optimization, VROOM/OSRM | `docs/knowledge/route-optimization.md` |
| Design patterns en uso | `docs/knowledge/design-patterns.md` |
| Tests, PHPUnit | `docs/knowledge/testing.md` |
| Todos los modulos | `docs/knowledge/index.md` |

## Reglas de Output

- **Maximo 200 lineas** en tu output final
- Si el analisis es extenso, escribe a `docs/superpowers/agent-outputs/` y retorna solo un resumen con la ruta al archivo
- **No copies codigo fuente completo** — referencia archivos y lineas
- Empieza siempre con un resumen ejecutivo de 5-10 lineas
