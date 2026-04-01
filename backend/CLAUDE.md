# Backend — Conventions and Architecture

<!-- GENERIC-START: Applies to any backend project -->
## Why These Rules Exist

This backend separates two worlds: **critical bounded contexts** where domain logic
must be portable and testable without a database (DDD pure), and **pragmatic contexts**
where Symfony ORM conventions are sufficient and faster. The rules below prevent
critical contexts from accumulating ORM coupling that makes domain logic untestable,
while keeping pragmatic contexts productive. Every rule traces back to one of these two goals.
<!-- GENERIC-END -->

<!-- PROJECT-SPECIFIC-START -->
## Architecture: Two Worlds

### Critical Contexts (Pure DDD)
Route Planning (Route, RouteStop, RouteSnapshot, RouteEvent), Shipment/Delivery
(Shipment, Parcel, DeliveryEvidence, POD), Route Optimization.

**WHY:** These contexts contain the business core — saved kilometers, delivery
proof, route correctness. Domain logic here must be unit-testable without a database.
ORM coupling makes that impossible.

**File placement:**
```
src/Domain/{Context}/Model/        # POPOs — no #[ORM\...], no UserInterface, no Validator constraints
src/Domain/{Context}/Repository/   # Interfaces only — no Doctrine dependency
src/Domain/{Context}/Service/      # Domain services depending on Repository interfaces
src/Domain/{Context}/Event/        # Immutable POPOs — no Symfony/Doctrine imports

src/Infrastructure/{Context}/Doctrine/   # Repository implementations, ORM mapping
src/Infrastructure/{Context}/Symfony/    # Controllers, commands, event listeners
```

**Dependency arrow:** `Controller → Application Service → Domain Interface ← Infrastructure`

### Pragmatic Contexts (Symfony)
Identity/Auth (User), Tenant Management (Customer), Fleet (Vehicle, Driver), Notifications.

Entities live in `src/Entity/` with ORM attributes. Repositories may depend on Doctrine
directly. This is intentional — these contexts don't contain business-critical domain logic.

### When Touching Coupled Code in a Critical Context

Existing entities with ORM attributes in critical contexts are **documented tech debt,
not examples to follow**. Before implementing a new feature in a critical context:

1. Extract a repository interface to `src/Domain/{Context}/Repository/`
2. Create a Doctrine implementation in `src/Infrastructure/{Context}/Doctrine/`
3. Change the service to depend on the interface (not concrete Doctrine class)
4. Implement your feature against the interface

This is mandatory — adding new code that deepens ORM coupling in critical contexts
is not acceptable, even if the surrounding code does it.
<!-- PROJECT-SPECIFIC-END -->

<!-- GENERIC-START -->
## Design Principles: One Question Per Class

Before writing a class, ask these five questions. They encode SOLID as a decision
process, not a checklist applied after the fact.

**"Does this class have one reason to change?"** (SRP)
Entities hold domain state and state transitions (`start()`, `finish()`, `markDelivered()`).
Persistence belongs in Infrastructure. Validation belongs in Value Objects or DTOs.
Security belongs in voters and authenticators — never inside an entity.

**"Can I extend this without modifying it?"** (OCP)
Multiple implementations → interface + registry or tagged services. Never `if/switch`
on type to select an implementation. New behavior = new class.

**"Does every implementation honor the full contract?"** (LSP)
If an implementation needs stubs or no-ops, the interface is too wide — split it.
Never `throw new \RuntimeException('Not supported')` inside an interface method.

**"Are clients forced to depend on methods they don't use?"** (ISP)
Interfaces: 1–5 cohesive methods. Prefer composition: `class X implements A, B`.
Marker interfaces (zero methods) are acceptable. Stubs signal ISP + LSP violated together.

**"Do high-level modules depend on abstractions?"** (DIP)
Domain services and application services depend on interfaces defined in the Domain layer.
Infrastructure implements those interfaces. In critical contexts: `EntityManagerInterface`
directly in a service constructor is prohibited — use `RepositoryInterface::save()`.
In pragmatic CRUD contexts, direct Doctrine dependency is acceptable.

### Known Violations (honest status)

| Class | Violation | Status |
|---|---|---|
| `User.php` | Mixes 5 responsibilities: identity, auth, roles, multi-tenancy, persistence lifecycle | Tech debt, no fix planned |
| `WebhookGpsProvider` | Stubs for `login()`, `getSessionCookie()`, `getDevices()` (LSP + ISP) | Fix when third GPS provider is added |
| `DeliveryService` | Depends on concrete `RouteStopRepository` and `ShipmentRepository` (DIP) | Fix in Shipment/Delivery migration sprint |
<!-- GENERIC-END -->

<!-- GENERIC-START -->
## Design Patterns: Problem First, Pattern Second

Patterns are tools, not recipes. Start from the problem.

**Decision checklist before applying any pattern:**
1. Is it necessary? Three clear lines beat a premature abstraction.
2. How many real implementations exist? If one, don't extract an interface "just in case" — wait for the second.
3. What are the trade-offs? Each indirection (interface, factory, proxy) adds cognitive cost.
4. Are there alternatives? Most problems have 2–3 viable patterns — evaluate before deciding.
5. Does it improve SOLID? A pattern that violates SOLID is probably the wrong pattern.

**Signals you chose wrong:**
- Added 3+ classes for a single real implementation → over-engineering
- Need to open 5 files to trace a simple flow → too much indirection
- A Facade has 10+ constructor dependencies → becoming a God Class
- Implemented Strategy with one implementation "just in case" → YAGNI
- Events make it impossible to trace what happens after a state change → over-decoupled

**What not to do:**
- `if ($type === 'x') return new X()` → use the Provider Framework (Factory + Registry already exists)
- Return null where a service is expected → Null Object
- Trigger side-effects directly inside the service that changes state → Domain Event + Listener
- Add cross-cutting behavior by modifying a class → Decorator or Proxy
<!-- GENERIC-END -->

<!-- PROJECT-SPECIFIC-START -->
### Patterns Already in This Codebase

Follow these when the problem type matches — but evaluate fit, don't copy blindly.

| Pattern | Use case | Scale |
|---|---|---|
| Provider Framework (Factory + Strategy + Adapter + TenantAware Proxy) | Per-tenant service resolution | 12 factories, 4 proxies |
| Domain Event + Listener | Domain side-effects decoupled from state changes | 13 events, 13 listeners |
| Command via Messenger | Async operations | 4 messages + handlers |
| Null Object | Graceful degradation when a service is absent | 12 `Null*` classes |
| Facade in Application layer | Complex multi-step workflows | `RoutePlanningService`, `DeliveryService` |

**Decision log:** After any non-trivial design decision (new abstraction, pattern choice,
architectural trade-off), add an entry to `docs/decisions/log.md`. Format:
```
### [YYYY-MM-DD] Brief context
- Problem: what needed solving
- Decision: pattern chosen and why
- Discarded alternatives: what else was considered
- Result: (fill post-implementation) did it work? what was learned?
```
<!-- PROJECT-SPECIFIC-END -->

<!-- GENERIC-START -->
## PHP Conventions
<!-- GENERIC-END -->

<!-- PROJECT-SPECIFIC-START -->
All PHP files: `declare(strict_types=1)` at the top.

**Doctrine:** Attribute mapping only (no XML/YAML). ORM 3.x requires
`naming_strategy: underscore_number_aware` in `doctrine.yaml` — missing this breaks
column name resolution silently.

**Controllers:** Attribute routing (`#[Route(...)]`).

**API errors:** Via `ApiErrorResponder` — never return raw exceptions or ad-hoc JSON shapes.

**DTOs:** Live in `src/Dto/`, implement `fromArray()` factory, carry Symfony Validator
constraints. DTOs are the validation boundary at the application layer entry point.

**Symfony version lock:** `extra.symfony.require=7.4.*` + `conflict >=8.0` in `composer.json`.
Do not upgrade Symfony components outside 7.4.x without explicit decision.

**Entity identity:** Internal PK = BIGINT `id` (joins, internal processing).
Public identifier = ULID `public_id` via `PublicIdTrait` (APIs, URLs, Mercure topics).
Never expose `id` in public APIs.

**Multi-tenancy:** `CustomerTenantFilter` (Doctrine SQL filter) + `CustomerScopedEntityInterface`.
Admin/Operator bypass automatically; `ROLE_CUSTOMER` and `ROLE_DRIVER` are scoped.
<!-- PROJECT-SPECIFIC-END -->

## Documentation Honesty

Documentation describes **what IS**, not what should be. When aspirational architecture
exists but isn't implemented yet, use these markers:

- Default voice = current state: "Entities use ORM attributes in `src/Entity/`"
- `[PLANNED]` = aspirational: "[PLANNED] Critical context entities will migrate to `src/Domain/{Context}/Model/` as POPOs"
- `[PARTIAL]` = partial: "[PARTIAL] Domain events are POPOs (13 events), but entities remain in `src/Entity/` with ORM attributes"

This applies to knowledge modules, FEATURES.md, and architecture docs.
It does NOT apply to behavioral instructions in CLAUDE.md (which describe desired behavior).
