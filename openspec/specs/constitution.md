# Project Constitution: transporte-tracking (mxo-track)

**Created**: 2026-02-19
**Last Updated**: 2026-02-19
**Version**: 1.0

---

## Purpose

This document defines the non-negotiable principles for this project. Every planning decision, implementation choice, and review criterion must be consistent with these principles. AI agents and human engineers read this file to understand the project's constraints before making any change.

---

## Architecture Principles

1. **Symfony 7.4 LTS Lock**: No Symfony 8.x components. All packages must stay within `7.4.*`. This is enforced via `composer.json` extra.symfony.require and conflict rules.
2. **Dual Identity Pattern**: All entities (except `CustomerVehicle`) use `PublicIdTrait` — BIGINT auto-increment for internal joins, ULID for public APIs. Internal IDs are never exposed externally.
3. **Multi-Tenant Isolation**: Customer data is scoped via `CustomerTenantFilter` (Doctrine SQL filter). Entities implementing `CustomerScopedEntityInterface` are automatically filtered for `ROLE_CUSTOMER` and `ROLE_DRIVER` users.
4. **Event-Based Shipment Lifecycle**: Shipment status is tracked via immutable `ShipmentEvent` records. The latest event determines current state.
5. **Idempotent Driver Actions**: All driver operations use `clientActionId` (UUID) for idempotency. Duplicates return 409 without side effects.

## Code Quality Standards

- **Test coverage minimum**: Not yet established (tests planned for future phases)
- **Test methodology**: TDD recommended for all new features
- **Linting**: `php -l` syntax check on all PHP files (`make lint`)
- **Type safety**: `declare(strict_types=1)` in all PHP files; PHP 8.4 typed properties and enums

## Technology Constraints

### Required
- PHP 8.4 for all backend code
- Symfony 7.4 LTS for all framework components
- PostgreSQL 16 for persistence
- Doctrine ORM 3.x with attribute mapping and `naming_strategy: underscore_number_aware`
- Redis 7 for session storage

### Prohibited
- Symfony 8.x components (enforced via composer conflict)
- Doctrine XML or YAML mapping (attribute-only)
- Exposing internal BIGINT IDs in any public API or URL
- Direct database queries in controllers (use repositories)

### Preferred (not mandatory)
- Composition over inheritance (traits for shared behavior)
- DTOs with `fromArray()` factory for API input validation
- Enums for bounded value sets (UserRole, RouteStatus, etc.)
- `ApiErrorResponder` for consistent error format

## Security Principles

1. **Active User Validation**: `UserChecker` blocks inactive users before authentication
2. **Rate Limiting**: Login throttled to 5 attempts per minute (sliding window)
3. **CSRF Protection**: Enabled globally for all forms
4. **Security Headers**: `SecurityHeadersSubscriber` adds X-Frame-Options, CSP, etc. to all responses
5. **Secrets never committed**: Environment variables for all secrets (APP_SECRET, JWT keys, DB credentials)
6. **Audit Trail**: Security-sensitive operations logged via `AuditLogger`

## API Conventions

- **Style**: RESTful for data APIs; Twig templates for admin/driver web pages
- **Identifiers**: All public endpoints use `{publicId}` (ULID) route parameters
- **Response format**: JSON for API endpoints, HTML for web endpoints
- **Error format**: Consistent via `ApiErrorResponder`
- **Authentication**: Session-based (form_login with CSRF)
- **Naming**: Driver API uses `shipment_public_id` (not `shipment_id`) in payloads

## Git & Workflow Conventions

- **Branch strategy**: Feature branches, merge via PRs
- **Commit format**: Conventional Commits (feat:, fix:, chore:, docs:)
- **PR requirements**: Tests passing (when available)

## What This File Is NOT

- This is NOT a style guide (that belongs in linter configs)
- This is NOT documentation (that belongs in docs/)
- This is NOT a feature spec (that belongs in openspec/)
- This IS the set of constraints that never change without explicit team agreement

---

**Location**: `openspec/specs/constitution.md`
**Loaded by**: `/workflows:plan` (Step 0), `/workflows:review` (Phase 4)
**Updated by**: `/workflows:compound` (only after team agreement)
