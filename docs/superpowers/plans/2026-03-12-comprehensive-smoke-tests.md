# Plan: Comprehensive Smoke Tests (No Database Required)

**Goal:** Add 7 automated smoke tests that verify the entire platform's infrastructure without requiring a database connection. All tests run locally in <5 seconds.

**Spec:** `docs/superpowers/specs/2026-03-12-comprehensive-smoke-tests-design.md`

**Architecture:** Symfony 7.4 LTS, PHPUnit 11, PHP 8.4
**Test location:** `backend/tests/Functional/Smoke/`
**Branch:** `claude/add-automated-tests-WwolE`

---

## File Structure

```
backend/tests/Functional/Smoke/
├── ContainerWiringTest.php          # Task 1
├── SecurityAccessControlTest.php    # Task 2
├── ConsoleCommandSmokeTest.php      # Task 3
├── DoctrineSchemaValidationTest.php # Task 4
├── RouteControllerIntegrityTest.php # Task 5
├── EnumConsistencyTest.php          # Task 6
├── DtoValidationTest.php            # Task 7
```

---

## Task 1: ContainerWiringTest

**File:** `backend/tests/Functional/Smoke/ContainerWiringTest.php`
**Time estimate:** ~5 min

### Step 1.1: Write failing test

Create `ContainerWiringTest` extending `KernelTestCase` with:

```php
public function testAllControllersAreInstantiable(): void
```

- Boot kernel with `self::bootKernel()`
- Use `static::getContainer()` (test container — all services public)
- Use `Finder` to find all `*Controller.php` files in `src/Controller/`
- For each, derive the FQCN and call `$container->get($fqcn)`
- Collect any `\Throwable` as failures
- Assert no failures

```php
public function testCriticalServicesResolve(): void
```

- Verify these interfaces/services resolve:
  - `Symfony\Component\Routing\RouterInterface`
  - `Doctrine\ORM\EntityManagerInterface`
  - `Twig\Environment`
  - `Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface`
  - `Symfony\Component\Validator\Validator\ValidatorInterface`
  - `Psr\Log\LoggerInterface`

### Step 1.2: Run test — verify it fails or passes

```bash
php vendor/bin/phpunit tests/Functional/Smoke/ContainerWiringTest.php --testdox
```

### Step 1.3: If failures, fix and re-run

### Step 1.4: Commit

```bash
git add tests/Functional/Smoke/ContainerWiringTest.php
git commit -m "test: add ContainerWiringTest — verify all controllers and critical services resolve"
```

- [ ] Task 1 complete

---

## Task 2: SecurityAccessControlTest

**File:** `backend/tests/Functional/Smoke/SecurityAccessControlTest.php`
**Time estimate:** ~5 min

### Step 2.1: Write test

Create `SecurityAccessControlTest` extending `KernelTestCase` with:

```php
public function testAdminRoutesRequireAdminRole(): void
```

- Boot kernel, get router
- Get all routes with `admin_` prefix
- For each, verify the route path starts with `/admin`
- Get security config from container parameter or extension
- Verify access_control has a rule `{ path: ^/admin, roles: ROLE_ADMIN }`

```php
public function testPublicRoutesDoNotRequireAuth(): void
```

- Verify routes for `/login`, `/track/`, `/locale/` match PUBLIC_ACCESS rules
- Read access_control from compiled security config

```php
public function testRoleHierarchyIsConsistent(): void
```

- Get role hierarchy from container
- Verify ROLE_ADMIN includes ROLE_OPERATOR and ROLE_DRIVER
- Verify ROLE_OPERATOR includes ROLE_CUSTOMER

### Step 2.2: Run and verify

```bash
php vendor/bin/phpunit tests/Functional/Smoke/SecurityAccessControlTest.php --testdox
```

### Step 2.3: Commit

```bash
git add tests/Functional/Smoke/SecurityAccessControlTest.php
git commit -m "test: add SecurityAccessControlTest — verify firewall, access control, role hierarchy"
```

- [ ] Task 2 complete

---

## Task 3: ConsoleCommandSmokeTest

**File:** `backend/tests/Functional/Smoke/ConsoleCommandSmokeTest.php`
**Time estimate:** ~5 min

### Step 3.1: Write test

Create `ConsoleCommandSmokeTest` extending `KernelTestCase` with:

```php
public function testAllCommandsShowHelpWithoutErrors(): void
```

- Boot kernel
- Get `Symfony\Bundle\FrameworkBundle\Console\Application`
- Get all registered commands
- Skip list: commands starting with `doctrine:`, `debug:`, `cache:`, `secrets:`, `server:` (framework internals)
- For each app command (starting with `app:` or custom names):
  - Create `CommandTester`
  - Run with `['--help' => true]`
  - Assert exit code 0

```php
public function testAppCommandsAreRegistered(): void
```

- Verify expected commands exist: `app:create-admin`, `app:demo-setup`, `app:simulate-gps`, etc.
- Assert count of app-specific commands > 5

### Step 3.2: Run and verify

```bash
php vendor/bin/phpunit tests/Functional/Smoke/ConsoleCommandSmokeTest.php --testdox
```

### Step 3.3: Commit

```bash
git add tests/Functional/Smoke/ConsoleCommandSmokeTest.php
git commit -m "test: add ConsoleCommandSmokeTest — verify all commands instantiate and show help"
```

- [ ] Task 3 complete

---

## Task 4: DoctrineSchemaValidationTest

**File:** `backend/tests/Functional/Smoke/DoctrineSchemaValidationTest.php`
**Time estimate:** ~5 min

### Step 4.1: Write test

Create `DoctrineSchemaValidationTest` extending `KernelTestCase` with:

```php
public function testEntityMappingsAreValid(): void
```

- Boot kernel
- Get `EntityManagerInterface` from container
- Use `$em->getMetadataFactory()->getAllMetadata()` to load all entity metadata
- Assert count > 10 (sanity check)
- For each metadata, verify:
  - Class exists
  - All mapped fields have valid types
  - Use Doctrine's `SchemaValidator::validateMapping()` — this validates mappings WITHOUT connecting to DB

```php
public function testNamingStrategyIsConfigured(): void
```

- Get Doctrine configuration
- Verify naming strategy is `underscore_number_aware` (project requirement from CLAUDE.md)

### Step 4.2: Run and verify

```bash
php vendor/bin/phpunit tests/Functional/Smoke/DoctrineSchemaValidationTest.php --testdox
```

### Step 4.3: Commit

```bash
git add tests/Functional/Smoke/DoctrineSchemaValidationTest.php
git commit -m "test: add DoctrineSchemaValidationTest — verify entity mappings and naming strategy"
```

- [ ] Task 4 complete

---

## Task 5: RouteControllerIntegrityTest

**File:** `backend/tests/Functional/Smoke/RouteControllerIntegrityTest.php`
**Time estimate:** ~5 min

### Step 5.1: Write test

Create `RouteControllerIntegrityTest` extending `KernelTestCase` with:

```php
public function testAllRoutesPointToExistingControllerMethods(): void
```

- Boot kernel, get router
- Iterate all routes
- For each route with a `_controller` default:
  - Parse `App\Controller\FooController::barAction` format
  - Verify class exists with `class_exists()`
  - Verify method exists with `method_exists()`
  - Verify method is public via `ReflectionMethod`
- Collect failures, assert empty

```php
public function testNoOrphanedControllerMethods(): void
```

- Find all public methods in controller classes that have `#[Route]` attributes
- Verify each has a corresponding route in the router
- This catches methods with route attributes that don't load (e.g., syntax error in attribute)

### Step 5.2: Run and verify

```bash
php vendor/bin/phpunit tests/Functional/Smoke/RouteControllerIntegrityTest.php --testdox
```

### Step 5.3: Commit

```bash
git add tests/Functional/Smoke/RouteControllerIntegrityTest.php
git commit -m "test: add RouteControllerIntegrityTest — verify route-to-controller bindings"
```

- [ ] Task 5 complete

---

## Task 6: EnumConsistencyTest

**File:** `backend/tests/Functional/Smoke/EnumConsistencyTest.php`
**Time estimate:** ~5 min

### Step 6.1: Write test

Create `EnumConsistencyTest` extending `TestCase` (no kernel needed) with:

```php
public function testAllEnumsHaveUniqueBackingValues(): void
```

- Use `Finder` to find all PHP files in `src/Enum/`
- For each, load the class via `require_once` + reflection
- If it's a `BackedEnum`, verify all `cases()` have unique `->value`

```php
public function testEnumsWithLabelsReturnNonEmptyStrings(): void
```

- For enums with `label()` or `getLabel()` method
- Call it on each case, verify non-empty string returned

### Step 6.2: Run and verify

```bash
php vendor/bin/phpunit tests/Functional/Smoke/EnumConsistencyTest.php --testdox
```

### Step 6.3: Commit

```bash
git add tests/Functional/Smoke/EnumConsistencyTest.php
git commit -m "test: add EnumConsistencyTest — verify enum backing values and labels"
```

- [ ] Task 6 complete

---

## Task 7: DtoValidationTest

**File:** `backend/tests/Functional/Smoke/DtoValidationTest.php`
**Time estimate:** ~5 min

### Step 7.1: Write test

Create `DtoValidationTest` extending `TestCase` (no kernel needed) with:

```php
public function testAllDtosHaveFromArrayFactory(): void
```

- Use `Finder` to find all PHP files in `src/Dto/`
- For each class, verify `fromArray()` static method exists
- Exclude base classes / interfaces if any

```php
public function testAllDtosHaveValidationConstraints(): void
```

- For each DTO class, use reflection to check for Symfony Validator attributes
  (`#[Assert\NotBlank]`, `#[Assert\Type]`, etc.)
- Verify at least one property or the class itself has a constraint

```php
public function testFromArrayWithEmptyArrayThrowsOrReturnsDto(): void
```

- For each DTO with `fromArray()`, call `ClassName::fromArray([])`
- Expect either: a valid DTO instance OR a `\TypeError`/`\InvalidArgumentException`
- Fail if it causes an unexpected fatal error or returns null

### Step 7.2: Run and verify

```bash
php vendor/bin/phpunit tests/Functional/Smoke/DtoValidationTest.php --testdox
```

### Step 7.3: Commit

```bash
git add tests/Functional/Smoke/DtoValidationTest.php
git commit -m "test: add DtoValidationTest — verify DTO factories and validation constraints"
```

- [ ] Task 7 complete

---

## Task 8: Final Verification

### Step 8.1: Run all smoke tests

```bash
php vendor/bin/phpunit tests/Functional/Smoke/ --exclude-group requires-db --testdox
```

Expected: All tests pass, execution < 5 seconds.

### Step 8.2: Run full test suite

```bash
php vendor/bin/phpunit --exclude-group requires-db
```

Expected: All 447+ tests pass with 0 failures.

### Step 8.3: Final commit and push

```bash
git push -u origin claude/add-automated-tests-WwolE
```

- [ ] Task 8 complete

---

## Verification Checklist

- [ ] All 7 new test files created
- [ ] All tests pass without database
- [ ] Execution time < 5 seconds for smoke tests
- [ ] No regressions in existing 447 tests
- [ ] Each test has clear failure messages
- [ ] Tests follow project conventions (strict_types, attributes)
- [ ] Code committed and pushed to branch
