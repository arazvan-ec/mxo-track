# Design: Comprehensive Smoke Tests (No Database Required)

**Date:** 2026-03-12
**Status:** Draft
**Goal:** Ensure the entire platform works without manual testing, using automated tests that run locally without a database.

## Problem

A broken route reference (`admin_route_planner` instead of `admin_route_planner_index`) caused a 500 error in production. The project has 447 unit tests but no systematic verification that the application infrastructure is correctly wired. Bugs in templates, service configuration, security rules, and Doctrine mappings can slip through undetected.

## Scope

All tests MUST run without a database (PostgreSQL). They verify application infrastructure at the container/config level, not at the HTTP request level.

## Existing Tests (already implemented)

| Test | What it detects |
|------|----------------|
| `TemplateRouteReferenceTest` | Twig templates referencing non-existent routes |
| `TwigTemplateCompilationTest` | Twig syntax errors in all templates |

## New Tests

### 1. ContainerWiringTest

**Purpose:** Verify all services resolve from the DI container without errors.

**What it detects:**
- Missing constructor arguments
- Interfaces without bound implementations
- Circular dependencies
- services.yaml misconfigurations
- Autoconfigure/autowire failures

**Approach:**
- Boot kernel in test environment
- Get all public service IDs from the container
- For each controller class in `src/Controller/`, verify it can be fetched from the container
- Verify critical service interfaces resolve (RouterInterface, EntityManagerInterface, etc.)

**No DB needed:** Container compilation is done at kernel boot, no queries executed.

### 2. SecurityAccessControlTest

**Purpose:** Verify firewall and access control rules are correctly configured.

**What it detects:**
- Admin routes accessible without ROLE_ADMIN
- Public routes incorrectly requiring authentication
- Missing firewall patterns
- Role hierarchy misconfiguration

**Approach:**
- Read security configuration from the compiled container
- Verify access_control rules cover all route prefixes
- Verify role hierarchy is consistent (ROLE_ADMIN > ROLE_OPERATOR > ROLE_CUSTOMER)
- Verify public routes (/login, /track/, /locale/) don't require auth
- Verify admin routes require ROLE_ADMIN

**No DB needed:** Inspects compiled security configuration, no HTTP requests.

### 3. ConsoleCommandSmokeTest

**Purpose:** Verify all console commands instantiate and show help without errors.

**What it detects:**
- Commands with broken dependencies
- Missing command configurations
- Broken command definitions (arguments, options)

**Approach:**
- Get all registered commands from the Application
- For each command, run `command --help` and verify exit code 0
- Skip commands that require DB connection in their `--help` output (none should)

**No DB needed:** `--help` only reads command metadata, doesn't execute logic.

### 4. DoctrineSchemaValidationTest

**Purpose:** Verify Doctrine entity mappings are valid.

**What it detects:**
- Invalid column types or names
- Broken entity relationships (missing inversedBy, bad targetEntity)
- Missing naming_strategy configuration
- Enum type mismatches
- PublicIdTrait not properly applied

**Approach:**
- Use `doctrine:schema:validate --skip-sync` equivalent via Doctrine's SchemaValidator
- Only validates mapping metadata, not database sync
- Verify all entities implement required interfaces (CustomerScopedEntityInterface where expected)

**No DB needed:** Validates ORM metadata in memory, no database connection.

### 5. RouteControllerIntegrityTest

**Purpose:** Verify route-to-controller bindings are valid.

**What it detects:**
- Routes pointing to deleted controllers
- Routes pointing to renamed/missing methods
- Controller methods with wrong signatures

**Approach:**
- Iterate all routes from the RouterInterface
- For each route with a `_controller` attribute, verify the class and method exist
- Verify controller methods are public

**No DB needed:** Inspects routing metadata only.

### 6. EnumConsistencyTest

**Purpose:** Verify all enums are internally consistent.

**What it detects:**
- Enum cases with duplicate values
- Enums missing expected interface implementations
- Label/display methods returning empty strings

**Approach:**
- Find all PHP enums in `src/Enum/`
- Verify each has unique backing values
- If enum has `label()` or `getLabel()` method, verify all cases return non-empty strings
- Verify enums used in Doctrine entities match their column types

**No DB needed:** Pure PHP reflection, no container needed.

### 7. DtoValidationTest

**Purpose:** Verify DTOs have proper validation constraints and factory methods.

**What it detects:**
- DTOs with `fromArray()` that don't handle required fields
- DTOs missing Symfony Validator constraints
- DTOs with mismatched property types vs constraint types

**Approach:**
- Find all classes in `src/Dto/`
- Verify each has `fromArray()` static factory (project convention)
- Verify each has at least one Symfony Validator constraint attribute
- Test `fromArray()` with empty array to verify it handles gracefully

**No DB needed:** Pure PHP instantiation and reflection.

## Test Organization

```
tests/Functional/Smoke/
├── AdminPageSmokeTest.php          (existing, requires-db)
├── PageSmokeTest.php               (existing, requires-db)
├── TemplateRouteReferenceTest.php  (existing, no DB)
├── TwigTemplateCompilationTest.php (existing, no DB)
├── ContainerWiringTest.php         (new, no DB)
├── SecurityAccessControlTest.php   (new, no DB)
├── ConsoleCommandSmokeTest.php     (new, no DB)
├── DoctrineSchemaValidationTest.php(new, no DB)
├── RouteControllerIntegrityTest.php(new, no DB)
├── EnumConsistencyTest.php         (new, no DB)
└── DtoValidationTest.php           (new, no DB)
```

## Running Tests

```bash
# All smoke tests (no DB)
php vendor/bin/phpunit tests/Functional/Smoke/ --exclude-group requires-db

# Including DB-dependent smoke tests
php vendor/bin/phpunit tests/Functional/Smoke/

# Full test suite
php vendor/bin/phpunit --exclude-group requires-db
```

## Success Criteria

- All 7 new tests pass without database
- Execution time < 5 seconds total
- Tests would have caught the original `admin_route_planner` bug
- Tests detect at least: broken service wiring, security misconfig, bad Doctrine mappings
- Zero false positives (no tests that fail due to environment differences)

## Implementation Notes (from spec review)

1. **DATABASE_URL must be syntactically valid** in `.env.test` even if DB doesn't exist. Doctrine defers actual connection but needs a parseable URL at kernel boot.
2. **ConsoleCommandSmokeTest** must maintain a skip-list for Doctrine commands (`doctrine:*`) that may attempt DB connection even on `--help`.
3. **ContainerWiringTest** must use `static::getContainer()` (test container, all services public) since controllers are private services in Symfony 7.4.
4. **DtoValidationTest** — `fromArray([])` throwing TypeError/exception IS the expected "graceful" behavior. Test should verify it throws, not that it returns silently.
5. **SecurityAccessControlTest** inspects static config only. Custom voters are out of scope (documented as non-goal).

## Non-Goals

- HTTP request-level testing (covered by existing requires-db tests)
- Business logic testing (covered by unit tests)
- External service integration testing (Traccar, Vroom, etc.)
- Performance testing
