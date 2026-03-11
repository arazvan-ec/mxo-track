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
cd backend && composer install          # Install dependencies
php bin/console about                   # Verify Symfony is working
php bin/console doctrine:migrations:migrate -n  # Run migrations
php bin/console doctrine:fixtures:load -n       # Load fixtures (admin user)
make lint                               # PHP syntax lint (all src files)
php vendor/bin/phpunit                  # Run tests
```

## Conventions

- All PHP files use `declare(strict_types=1)`
- Doctrine mappings via PHP attributes (not XML/YAML)
- Doctrine ORM 3.x: `naming_strategy: underscore_number_aware` required in doctrine.yaml
- Controllers use attribute routing
- API error responses via `ApiErrorResponder`
- DTOs in `src/Dto/` with `fromArray()` factory + Symfony Validator constraints
- Symfony 7.4 lock enforced: `extra.symfony.require=7.4.*`, `conflict >=8.0`

## Critical Patterns

### Entity Identity (mandatory)

- **Internal PK**: BIGINT auto-increment (`id`) — joins, internal processing
- **Public ID**: ULID (`public_id`) via `PublicIdTrait` — APIs, URLs, Mercure topics
- **NEVER expose internal `id` in public APIs**

### Multi-Tenancy

- `CustomerTenantFilter` (Doctrine SQL filter) + `CustomerScopedEntityInterface`
- Admin/Operator bypass; ROLE_CUSTOMER and ROLE_DRIVER scoped

### Role Hierarchy

```
ROLE_ADMIN > ROLE_OPERATOR > ROLE_CUSTOMER
ROLE_ADMIN > ROLE_DRIVER
```

## Knowledge Modules (consultar bajo demanda)

Antes de trabajar en un subsistema, **LEE el módulo relevante** en `docs/knowledge/`:

| Si vas a trabajar en... | Lee primero |
|------------------------|-------------|
| Entidades, relaciones, migraciones, enums | `docs/knowledge/domain-model.md` |
| Providers, factories, resolución per-tenant | `docs/knowledge/provider-framework.md` |
| Controllers, DTOs, APIs, endpoints | `docs/knowledge/api-surface.md` |
| Docker, Railway, variables de entorno | `docs/knowledge/deployment.md` |
| Tests, PHPUnit, coverage | `docs/knowledge/testing.md` |
| Mercure, SSE, tokens JWT | `docs/knowledge/realtime.md` |
| Traccar, posiciones GPS, simulación | `docs/knowledge/gps-tracking.md` |
| SMS, WhatsApp, push, webhooks | `docs/knowledge/notifications.md` |
| Claude AI, embeddings, ML | `docs/knowledge/ai-ml.md` |
| VROOM, OSRM, capacidad, rutas | `docs/knowledge/route-optimization.md` |
| Roles, multi-tenancy, CSRF, seguridad | `docs/knowledge/security.md` |
| Skills de Superpowers (completo) | `docs/knowledge/superpowers-skills.md` |
| Índice completo de módulos | `docs/knowledge/index.md` |
| Análisis previos del codebase | `docs/analysis/` |

**Regla:** No duplicar info entre CLAUDE.md y los módulos. Al modificar un subsistema, actualizar el módulo correspondiente.

## Features Document

`docs/FEATURES.md` — descripción completa de todas las características. **Debe mantenerse actualizado** con cada PR que añada, modifique o elimine funcionalidad.

## Backlog Arquitectónico

### [2026-03-11] Providers configurables: Proxy + Factory vs alternativas

**Estado:** Pendiente de implementación
**Decisión:** Transparent Proxy + Provider Factory + CustomerIntegration entity
**Spec:** `docs/superpowers/specs/2026-03-11-user-configurable-providers-design.md`
**Plan:** `docs/superpowers/plans/2026-03-11-user-configurable-providers.md`
**Trigger para revisitar:** Si boilerplate de proxies > 6 servicios, considerar codegen o proxy genérico.

### [2026-03-11] GpsDeviceProviderInterface: Métodos Traccar-específicos

**Estado:** Pendiente
**Decisión:** Stubs en WebhookGpsProvider (login→no-op, getSessionCookie→null)
**Trigger:** Al implementar tercer provider GPS, refactoring obligatorio.

### [2026-03-11] Mercure listeners usan HubInterface directamente

**Estado:** Pendiente
**Decisión:** Deuda técnica documentada. Refactorizar antes de configurar tenant con HttpPolling.

### [2026-03-11] Sin encriptación de credenciales en CustomerIntegration

**Estado:** Pendiente
**Trigger:** Antes de producción con customers configurando API keys de terceros.

---

## Superpowers Skills

Skills que definen el flujo de trabajo y disciplina de desarrollo. **OBLIGATORIO invocar la skill relevante antes de cualquier acción.**

**Referencia completa:** `docs/knowledge/superpowers-skills.md` — **LEE el módulo completo** antes de aplicar cualquier skill. Los triggers aquí son solo índice de navegación.

### Skill 1: Using Superpowers
**Trigger:** Inicio de cualquier conversación — verificar si alguna skill aplica antes de responder.

### Skill 2: Brainstorming
**Trigger:** Cualquier trabajo creativo — crear features, componentes, modificar comportamiento. Explorar intención y diseño ANTES de implementar.

### Skill 3: Writing Plans
**Trigger:** Tienes un diseño validado y necesitas crear plan de implementación detallado.
**Output:** `docs/superpowers/plans/YYYY-MM-DD-<feature>.md`

### Skill 4: Executing Plans
**Trigger:** Tienes un plan escrito para ejecutar. Cargar, revisar críticamente, ejecutar todas las tareas.

### Skill 5: Subagent-Driven Development
**Trigger:** Ejecutar plan con tareas independientes en la sesión actual. Un subagente por tarea + doble review (spec + quality).

### Skill 6: Dispatching Parallel Agents
**Trigger:** 2+ tareas independientes sin estado compartido. Un agente por dominio de problema.

### Skill 7: Test-Driven Development
**Trigger:** Implementar cualquier feature o bugfix. TEST PRIMERO, siempre.
**Iron Law:** NO PRODUCTION CODE WITHOUT A FAILING TEST FIRST. Red → Green → Refactor.

### Skill 8: Systematic Debugging
**Trigger:** Cualquier bug, test failure, comportamiento inesperado. Root cause ANTES de fix.
**Iron Law:** NO FIXES WITHOUT ROOT CAUSE INVESTIGATION FIRST.

### Skill 9: Verification Before Completion
**Trigger:** A punto de reclamar que algo está completo. Ejecutar verificación ANTES de reclamar.
**Iron Law:** NO COMPLETION CLAIMS WITHOUT FRESH VERIFICATION EVIDENCE.

### Skill 10: Receiving Code Review
**Trigger:** Recibir feedback de code review. Verificar técnicamente antes de implementar.

### Skill 11: Requesting Code Review
**Trigger:** Completar tarea mayor, antes de merge. Dispatch code-reviewer subagent.

### Skill 12: Finishing a Development Branch
**Trigger:** Implementación completa. Verify tests → Present options → Execute → Clean up.

### Skill 13: Using Git Worktrees
**Trigger:** Feature work que necesita aislamiento. Verificar .gitignore antes de crear worktree.

### Skill 14: Writing Skills
**Trigger:** Crear o editar skills. TDD aplicado a documentación de procesos.

---

### Problemas Conocidos

**Fallos de infraestructura en subagentes:** Si un subagente falla con errores de runtime (`undefined is not an object`), no reintentar — ejecutar la tarea en el hilo principal o lanzar nuevo subagente.

**Error "tool_use ids must be unique":** Bug del cliente en sesiones largas. Mitigación: commits frecuentes, TodoWrite actualizado, tareas atómicas. Recuperación: `/clear` o nueva sesión.
